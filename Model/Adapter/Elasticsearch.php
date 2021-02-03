<?php
/**
 * @category  Syncit Group Elastic Search Indexer
 * @package   SyncitGroup_Indexer
 * @copyright Copyright (c) 2021 Bhavin
 * @author    Bhavin
 */

namespace SyncitGroup\Indexer\Model\Adapter;
use Magento\Elasticsearch\Model\Adapter\BatchDataMapperInterface;
use Magento\Elasticsearch\Model\Adapter\FieldMapperInterface;

/**
 * Elasticsearch adapter
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Elasticsearch {
	/**#@+
		     * Text flags for Elasticsearch bulk actions
	*/
	const BULK_ACTION_INDEX = 'index';
	const BULK_ACTION_CREATE = 'create';
	const BULK_ACTION_DELETE = 'delete';
	const BULK_ACTION_UPDATE = 'update';
	/**#@-*/

	/**
	 * Buffer for total fields limit in mapping.
	 */
	private const MAPPING_TOTAL_FIELDS_BUFFER_LIMIT = 1000;

	/**#@-*/
	protected $connectionManager;

	/**
	 * @var \Magento\Elasticsearch\Model\Adapter\Index\IndexNameResolver
	 */
	protected $indexNameResolver;

	/**
	 * @var FieldMapperInterface
	 */
	protected $fieldMapper;

	/**
	 * @var \Magento\Elasticsearch\Model\Config
	 */
	protected $clientConfig;

	/**
	 * @var \Magento\AdvancedSearch\Model\Client\ClientInterface
	 */
	protected $client;

	/**
	 * @var \Magento\Elasticsearch\Model\Adapter\Index\BuilderInterface
	 */
	protected $indexBuilder;

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * @var array
	 */
	protected $preparedIndex = [];

	/**
	 * @var BatchDataMapperInterface
	 */
	private $batchDocumentDataMapper;

	/**
	 * @param \Magento\Elasticsearch\SearchAdapter\ConnectionManager $connectionManager
	 * @param FieldMapperInterface $fieldMapper
	 * @param \Magento\Elasticsearch\Model\Config $clientConfig
	 * @param Index\BuilderInterface $indexBuilder
	 * @param \Psr\Log\LoggerInterface $logger
	 * @param Index\IndexNameResolver $indexNameResolver
	 * @param BatchDataMapperInterface $batchDocumentDataMapper
	 * @param array $options
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
	public function __construct(
		\Magento\Elasticsearch\SearchAdapter\ConnectionManager $connectionManager,
		\SyncitGroup\Indexer\SearchAdapter\ConnectionManager $syncitgroupConnectionManager,
		FieldMapperInterface $fieldMapper,
		\Magento\Elasticsearch\Model\Config $clientConfig,
		\Magento\Elasticsearch\Model\Adapter\Index\BuilderInterface $indexBuilder,
		\Psr\Log\LoggerInterface $logger,
		\Magento\Elasticsearch\Model\Adapter\Index\IndexNameResolver $indexNameResolver,
		BatchDataMapperInterface $batchDocumentDataMapper,
		\SyncitGroup\Indexer\Helper\Data $helper,
		$options = []
	) {
		$this->connectionManager = $connectionManager;
		$this->fieldMapper = $fieldMapper;
		$this->clientConfig = $clientConfig;
		$this->helper = $helper;
		$this->indexBuilder = $indexBuilder;
		$this->logger = $logger;
		$this->indexNameResolver = $indexNameResolver;
		$this->batchDocumentDataMapper = $batchDocumentDataMapper;
		$this->syncitgroupConnectionManager = $syncitgroupConnectionManager;

		try {
			if ($this->helper->isExtentionEnable()) {
				$this->client = $this->syncitgroupConnectionManager->getConnection($options);
			} else {
				$this->client = $this->connectionManager->getConnection($options);
			}
		} catch (\Exception $e) {
			$this->logger->critical($e);
			throw new \Magento\Framework\Exception\LocalizedException(
				__('The search failed because of a search engine misconfiguration.')
			);
		}
	}

	/**
	 * Retrieve Elasticsearch server status
	 *
	 * @return bool
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
	public function ping() {
		try {
			$response = $this->client->ping();
		} catch (\Exception $e) {
			throw new \Magento\Framework\Exception\LocalizedException(
				__('Could not ping search engine: %1', $e->getMessage())
			);
		}
		return $response;
	}

	/**
	 * Create Elasticsearch documents by specified data
	 *
	 * @param array $documentData
	 * @param int $websiteId
	 * @return array
	 */
	public function prepareDocsPerStore(array $documentData, $websiteId) {
		$documents = [];
		if (count($documentData)) {
			$documents = $this->batchDocumentDataMapper->map(
				$documentData,
				$websiteId
			);
		}
		return $documents;
	}

	/**
	 * Add prepared Elasticsearch documents to Elasticsearch index
	 *
	 * @param array $documents
	 * @param int $websiteId
	 * @param string $mappedIndexerId
	 * @return $this
	 * @throws \Exception
	 */
	public function addDocs(array $documents, $websiteId, $mappedIndexerId) {
		if (count($documents)) {
			try {
				$indexName = $this->indexNameResolver->getIndexName($websiteId, $mappedIndexerId, $this->preparedIndex);
				$bulkIndexDocuments = $this->getDocsArrayInBulkIndexFormat($documents, $indexName);
				$this->client->bulkQuery($bulkIndexDocuments);
			} catch (\Exception $e) {
				$this->logger->critical($e);
				throw $e;
			}
		}

		return $this;
	}

	/**
	 * Removes all documents from Elasticsearch index
	 *
	 * @param int $websiteId
	 * @param string $mappedIndexerId
	 * @return $this
	 */
	public function cleanIndex($websiteId, $mappedIndexerId) {
		// needed to fix bug with double indices in alias because of second reindex in same process
		unset($this->preparedIndex[$websiteId]);

		$this->checkIndex($websiteId, $mappedIndexerId, true);
		$indexName = $this->indexNameResolver->getIndexName($websiteId, $mappedIndexerId, $this->preparedIndex);

		// prepare new index name and increase version
		$indexPattern = $this->indexNameResolver->getIndexPattern($websiteId, $mappedIndexerId);
		$version = (int) (str_replace($indexPattern, '', $indexName));
		$newIndexName = $indexPattern . (++$version);

		// remove index if already exists
		if ($this->client->indexExists($newIndexName)) {
			$this->client->deleteIndex($newIndexName);
		}

		// prepare new index
		$this->prepareIndex($websiteId, $newIndexName, $mappedIndexerId);

		return $this;
	}

	/**
	 * Delete documents from Elasticsearch index by Ids
	 *
	 * @param array $documentIds
	 * @param int $websiteId
	 * @param string $mappedIndexerId
	 * @return $this
	 * @throws \Exception
	 */
	public function deleteDocs(array $documentIds, $websiteId, $mappedIndexerId) {
		try {
			$this->checkIndex($websiteId, $mappedIndexerId, false);
			$indexName = $this->indexNameResolver->getIndexName($websiteId, $mappedIndexerId, $this->preparedIndex);
			$bulkDeleteDocuments = $this->getDocsArrayInBulkIndexFormat(
				$documentIds,
				$indexName,
				self::BULK_ACTION_DELETE
			);
			$this->client->bulkQuery($bulkDeleteDocuments);
		} catch (\Exception $e) {
			$this->logger->critical($e);
			throw $e;
		}

		return $this;
	}

	/**
	 * Reformat documents array to bulk format
	 *
	 * @param array $documents
	 * @param string $indexName
	 * @param string $action
	 * @return array
	 */
	protected function getDocsArrayInBulkIndexFormat(
		$documents,
		$indexName,
		$action = self::BULK_ACTION_INDEX
	) {
		$bulkArray = [
			'index' => $indexName,
			'type' => $this->clientConfig->getEntityType(),
			'body' => [],
			'refresh' => true,
		];

		foreach ($documents as $id => $document) {
			$bulkArray['body'][] = [
				$action => [
					'_id' => $id,
					'_type' => $this->clientConfig->getEntityType(),
					'_index' => $indexName,
				],
			];
			if ($action == self::BULK_ACTION_INDEX) {
				$bulkArray['body'][] = $document;
			}
		}

		return $bulkArray;
	}

	/**
	 * Checks whether Elasticsearch index and alias exists.
	 *
	 * @param int $websiteId
	 * @param string $mappedIndexerId
	 * @param bool $checkAlias
	 *
	 * @return $this
	 */
	public function checkIndex(
		$websiteId,
		$mappedIndexerId,
		$checkAlias = true
	) {
		// create new index for store
		$indexName = $this->indexNameResolver->getIndexName($websiteId, $mappedIndexerId, $this->preparedIndex);
		if (!$this->client->indexExists($indexName)) {
			$this->prepareIndex($websiteId, $indexName, $mappedIndexerId);
		}

		// add index to alias
		if ($checkAlias) {
			$namespace = $this->indexNameResolver->getIndexNameForAlias($websiteId, $mappedIndexerId);
			if (!$this->client->existsAlias($namespace, $indexName)) {
				$this->client->updateAlias($namespace, $indexName);
			}
		}
		return $this;
	}

	/**
	 * Update Elasticsearch alias for new index.
	 *
	 * @param int $websiteId
	 * @param string $mappedIndexerId
	 * @return $this
	 */
	public function updateAlias($websiteId, $mappedIndexerId) {
		if (!isset($this->preparedIndex[$websiteId])) {
			return $this;
		}

		$oldIndex = $this->indexNameResolver->getIndexFromAlias($websiteId, $mappedIndexerId);
		if ($oldIndex == $this->preparedIndex[$websiteId]) {
			$oldIndex = '';
		}

		$this->client->updateAlias(
			$this->indexNameResolver->getIndexNameForAlias($websiteId, $mappedIndexerId),
			$this->preparedIndex[$websiteId],
			$oldIndex
		);

		// remove obsolete index
		if ($oldIndex) {
			$this->client->deleteIndex($oldIndex);
		}

		return $this;
	}

	/**
	 * Create new index with mapping.
	 *
	 * @param int $websiteId
	 * @param string $indexName
	 * @param string $mappedIndexerId
	 * @return $this
	 */
	protected function prepareIndex($websiteId, $indexName, $mappedIndexerId) {
		$this->indexBuilder->setStoreId($websiteId);
		$settings = $this->indexBuilder->build();
		$allAttributeTypes = $this->fieldMapper->getAllAttributesTypes(
			[
				'entityType' => $mappedIndexerId,
				// Use store id instead of website id from context for save existing fields mapping.
				// In future websiteId will be eliminated due to index stored per store
				'websiteId' => $websiteId,
			]
		);
		$settings['index']['mapping']['total_fields']['limit'] = $this->getMappingTotalFieldsLimit($allAttributeTypes);
		$this->client->createIndex($indexName, ['settings' => $settings]);
		$this->client->addFieldsMapping(
			$allAttributeTypes,
			$indexName,
			$this->clientConfig->getEntityType()
		);
		$this->preparedIndex[$websiteId] = $indexName;
		return $this;
	}

	/**
	 * Get total fields limit for mapping.
	 *
	 * @param array $allAttributeTypes
	 * @return int
	 */
	private function getMappingTotalFieldsLimit(array $allAttributeTypes): int {
		return count($allAttributeTypes) + self::MAPPING_TOTAL_FIELDS_BUFFER_LIMIT;
	}
}
