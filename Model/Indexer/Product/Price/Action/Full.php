<?php
/**
 * @category  Syncit Group Elastic Search Indexer
 * @package   SyncitGroup_Indexer
 * @copyright Copyright (c) 2021 Bhavin
 * @author    Bhavin
 */

declare (strict_types = 1);

namespace SyncitGroup\Indexer\Model\Indexer\Product\Price\Action;

use Magento\Catalog\Model\Indexer\Product\Price\DimensionCollectionFactory;
use Magento\Catalog\Model\Indexer\Product\Price\DimensionModeConfiguration;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\DefaultPrice;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Factory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Elasticsearch\Model\Adapter\Index\IndexNameResolver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Indexer\DimensionalIndexerInterface;
use Magento\Framework\Indexer\IndexStructureInterface;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Model\StoreManagerInterface;
use SyncitGroup\Indexer\Model\Adapter\Elasticsearch;
use SyncitGroup\Indexer\Model\Indexer\Product\Price\AbstractAction;
use SyncitGroup\Indexer\Model\Indexer\Product\Price\TableMaintainer;

/**
 * Class Full reindex action
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Full extends AbstractAction {
	/**
	 * @var DimensionCollectionFactory
	 */
	private $dimensionCollectionFactory;
	/**
	 * @var TableMaintainer
	 */
	private $dimensionTableMaintainer;
	/**
	 * @var mixed
	 */
	private $elasticsearchAdapter;
	/**
	 * @var mixed
	 */
	private $configurable;
	/**
	 * @var mixed
	 */
	private $grouped;
	/**
	 * @param ScopeConfigInterface $config
	 * @param StoreManagerInterface $storeManager
	 * @param CurrencyFactory $currencyFactory
	 * @param TimezoneInterface $localeDate
	 * @param DateTime $dateTime
	 * @param Type $catalogProductType
	 * @param Factory $indexerPriceFactory
	 * @param DefaultPrice $defaultIndexerResource
	 * @param DimensionCollectionFactory $dimensionCollectionFactory
	 * @param nullTableMaintainer $dimensionTableMaintainer
	 * @param nullCollectionFactory $productCollectionFactory
	 * @param Configurable $configurable
	 * @param Grouped $grouped
	 * @param Elasticsearch $elasticsearchAdapter
	 * @param IndexStructureInterface $indexStructure
	 */
	public function __construct(
		ScopeConfigInterface $config,
		StoreManagerInterface $storeManager,
		CurrencyFactory $currencyFactory,
		TimezoneInterface $localeDate,
		DateTime $dateTime,
		Type $catalogProductType,
		Factory $indexerPriceFactory,
		DefaultPrice $defaultIndexerResource,
		DimensionCollectionFactory $dimensionCollectionFactory = null,
		TableMaintainer $dimensionTableMaintainer = null,
		CollectionFactory $productCollectionFactory,
		Configurable $configurable,
		Grouped $grouped,
		Elasticsearch $elasticsearchAdapter,
		IndexStructureInterface $indexStructure,
		IndexNameResolver $indexNameResolver
	) {
		parent::__construct(
			$config,
			$storeManager,
			$currencyFactory,
			$localeDate,
			$dateTime,
			$catalogProductType,
			$indexerPriceFactory,
			$defaultIndexerResource
		);
		$this->indexStructure = $indexStructure;
		$this->indexNameResolver = $indexNameResolver;
		$this->elasticsearchAdapter = $elasticsearchAdapter;
		$this->configurable = $configurable;
		$this->grouped = $grouped;
		$this->productCollectionFactory = $productCollectionFactory;
		$this->dimensionCollectionFactory = $dimensionCollectionFactory ?: ObjectManager::getInstance()->get(
			DimensionCollectionFactory::class
		);
		$this->dimensionTableMaintainer = $dimensionTableMaintainer ?: ObjectManager::getInstance()->get(
			TableMaintainer::class
		);
	}

	/**
	 * Execute Full reindex
	 *
	 * @param array|int|null $ids
	 * @return void
	 * @throws \Exception
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function execute($ids = null): void {
		try {
			$this->truncateTables();
			foreach ($this->dimensionCollectionFactory->create(DimensionModeConfiguration::DIMENSION_WEBSITE) as $dimension) {
				$this->reindexByBatchWithDimensions($dimension);
			}
		} catch (\Exception $e) {
			throw new LocalizedException(__($e->getMessage()), $e);
		}
	}
	/**
	 * @param array $changedIds
	 */
	public function _reindexRows($changedIds = []) {
		$connection = $this->_defaultIndexerResource->getConnection();
		$connection->delete($table, ["product_id in (" . implode(",", $changedIds) . ")"]);
		foreach ($this->dimensionCollectionFactory->create(DimensionModeConfiguration::DIMENSION_WEBSITE) as $dimension) {
			$this->reindexByBatchWithDimensionsByProductIds($dimension);
		}
	}
	/**
	 * Truncate replica tables by dimensions
	 *
	 * @return void
	 * @throws \Exception
	 */
	private function truncateTables(): void {
		foreach ($this->dimensionCollectionFactory->create(DimensionModeConfiguration::DIMENSION_WEBSITE) as $dimension) {
			$this->dimensionTableMaintainer->createTablesForDimensions($dimension);
			$dimensionTable = $this->dimensionTableMaintainer->getMainTable($dimension);
			$this->_defaultIndexerResource->getConnection()->truncateTable($dimensionTable);
		}
	}
	/**
	 * Reindex by batch for new 'Dimensional' price indexer
	 *
	 * @param DimensionalIndexerInterface $priceIndexer
	 * @param Select $batchQuery
	 * @param array $dimensions
	 *
	 * @return void
	 * @throws \Exception
	 */
	private function reindexByBatchWithDimensions(
		array $dimension
	): void{
		$products = $this->productCollectionFactory->create();
		$products->addWebsiteFilter([$dimension['ws']->getValue()]);
		$dimensionTable = $this->dimensionTableMaintainer->getMainTable($dimension);
		$connection = $this->_defaultIndexerResource->getConnection();
		$indexData = [];
		foreach ($products as $product) {
			$data['product_id'] = $product->getId();
			$data['min_price'] = $product->getId();
			$data['parent_id'] = 0;
			if ($product->getTypeId() == "simple") {
				$data['parent_id'] = $this->getParentId($product->getId(), $product);
			}
			$indexData[$product->getId()] = $data;
		}
		$this->saveIndex($dimension, $indexData);
		foreach (array_chunk($indexData, 200) as $chunkData) {
			$connection->insertMultiple($dimensionTable, $chunkData);

		}
	}
	/**
	 * @param array $dimension
	 */
	private function reindexByBatchWithDimensionsByProductIds(
		array $dimension,
		array $ids
	): void{
		$products = $this->productCollectionFactory->create();
		$products->addIdFilter($ids);
		$products->addWebsiteFilter([$dimension['ws']->getValue()]);
		$dimensionTable = $this->dimensionTableMaintainer->getMainTable($dimension);
		$connection = $this->_defaultIndexerResource->getConnection();
		$indexData = [];
		foreach ($products as $product) {
			$data['product_id'] = $product->getId();
			$data['min_price'] = $product->getId();
			$data['parent_id'] = 0;
			if ($product->getTypeId() == "simple") {
				$data['parent_id'] = $this->getParentId($product->getId(), $product);
			}
			$indexData[$product->getId()] = $data;
		}
		$this->deleteIndex($dimension, $ids);
		foreach (array_chunk($indexData, 200) as $chunkData) {
			$connection->insertMultiple($dimensionTable, $chunkData);
		}
	}

	public function deleteAllIndex() {
		$this->truncateTables();
		foreach ($this->dimensionCollectionFactory->create(DimensionModeConfiguration::DIMENSION_WEBSITE) as $dimension) {
			$this->cleanIndex($dimension);
		}
	}
	/**
	 * @param $childId
	 * @return mixed
	 */
	public function getParentId($childId, $productModel) {
		/* for simple product of configurable product */
		$product = $this->configurable->getParentIdsByChild($childId);
		if (isset($product[0])) {
			return $product[0];
		}

		/* for simple product of Group product */
		$parentId = $this->grouped->getParentIdsByChild($childId);
		if (!$parentId) {
			/* or for Group/Bundle Product */
			$parentId = $productModel->getTypeInstance()->getParentIdsByChild($childId);
		}

		return $parentId;
	}

	/**
	 * @inheritdoc
	 */
	public function saveIndex($dimensions, $documents) {
		$websiteId = $dimensions['ws']->getValue();
		foreach (array_chunk($documents, 200) as $chunkData) {
			$docs = $this->elasticsearchAdapter->prepareDocsPerStore($chunkData, $websiteId);
			$this->elasticsearchAdapter->addDocs($docs, $websiteId, $this->getIndexerId());
		}
		$this->elasticsearchAdapter->updateAlias($websiteId, $this->getIndexerId());
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function deleteIndex($dimensions, $documents) {
		$websiteId = $dimensions['ws']->getValue();
		$documentIds = [];
		foreach ($documents as $document) {
			$documentIds[$document] = $document;
		}
		$this->elasticsearchAdapter->deleteDocs($documentIds, $websiteId, $this->getIndexerId());
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function cleanIndex($dimensions) {
		$this->indexStructure->delete($this->getIndexerId(), $dimensions);
		$this->indexStructure->create($this->getIndexerId(), [], $dimensions);
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function isAvailable($dimensions = []) {
		return $this->elasticsearchAdapter->ping();
	}

	/**
	 * Returns indexer id.
	 *
	 * @return string
	 */
	private function getIndexerId() {
		return $this->indexNameResolver->getIndexMapping('syncitgroup');
	}
}
