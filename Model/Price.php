<?php
/**
 * @category  Syncit Group Elastic Search Indexer
 * @package   SyncitGroup_Indexer
 * @copyright Copyright (c) 2021 Bhavin
 * @author    Bhavin
 */
namespace SyncitGroup\Indexer\Model;

use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use SyncitGroup\Indexer\Model\Indexer\Product\Price\Action\Full as FullAction;
use SyncitGroup\Indexer\Model\Indexer\Product\Price\Action\Row as RowAction;
use SyncitGroup\Indexer\Model\Indexer\Product\Price\Action\Rows as RowsAction;

/**
 * Price indexer
 */
class Price implements IndexerActionInterface, MviewActionInterface {
	/**
	 * @var RowAction
	 */
	protected $_productPriceIndexerRow;

	/**
	 * @var RowsAction
	 */
	protected $_productPriceIndexerRows;

	/**
	 * @var FullAction
	 */
	protected $_productPriceIndexerFull;

	/**
	 * @var CacheContext
	 */
	private $cacheContext;

	/**
	 * @param RowAction $productPriceIndexerRow
	 * @param RowsAction $productPriceIndexerRows
	 * @param FullAction $productPriceIndexerFull
	 * @param CacheContext $cacheContext
	 */
	public function __construct(
		RowAction $productPriceIndexerRow,
		RowsAction $productPriceIndexerRows,
		FullAction $productPriceIndexerFull,
		CacheContext $cacheContext
	) {
		$this->_productPriceIndexerRow = $productPriceIndexerRow;
		$this->_productPriceIndexerRows = $productPriceIndexerRows;
		$this->_productPriceIndexerFull = $productPriceIndexerFull;
		$this->cacheContext = $cacheContext;
	}

	/**
	 * Execute materialization on ids entities
	 *
	 * @param int[] $ids
	 * @return void
	 */
	public function execute($ids) {
		$this->_productPriceIndexerRows->execute($ids);
		$this->cacheContext->registerEntities(ProductModel::CACHE_TAG, $ids);
	}

	/**
	 * Execute full indexation
	 *
	 * @return void
	 */
	public function executeFull() {
		$this->_productPriceIndexerFull->execute();
		$this->cacheContext->registerTags(
			[
				CategoryModel::CACHE_TAG,
				ProductModel::CACHE_TAG,
			]
		);
	}

	/**
	 * Execute partial indexation by ID list
	 *
	 * @param int[] $ids
	 * @return void
	 */
	public function executeList(array $ids) {
		$this->_productPriceIndexerRows->execute($ids);
		$this->cacheContext->registerEntities(ProductModel::CACHE_TAG, $ids);
	}

	/**
	 * Execute partial indexation by ID
	 *
	 * @param int $id
	 * @return void
	 */
	public function executeRow($id) {
		$this->_productPriceIndexerRow->execute($id);
		$this->cacheContext->registerEntities(ProductModel::CACHE_TAG, [$id]);
	}
	/**
	 * Execute partial indexation by ID
	 *
	 * @param int $id
	 * @return void
	 */
	public function deleteIndexes() {
		$this->_productPriceIndexerFull->deleteAllIndex();
		$this->cacheContext->registerEntities(ProductModel::CACHE_TAG, []);
	}
}