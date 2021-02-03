<?php
/**
 * @category  Syncit Group Elastic Search Indexer
 * @package   SyncitGroup_Indexer
 * @copyright Copyright (c) 2021 Bhavin
 * @author    Bhavin
 */

namespace SyncitGroup\Indexer\Ui\DataProvider\Indexer;

use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Indexer\ConfigInterface;
use Magento\Framework\Indexer\IndexerRegistry;

class DataCollection extends Collection {
	/**
	 * @var ConfigInterface
	 */
	private $config;

	/**
	 * @var IndexerRegistry
	 */
	private $indexerRegistry;

	/**
	 * @param EntityFactoryInterface $entityFactory
	 * @param ConfigInterface $config
	 * @param IndexerRegistry $indexerRegistry
	 */
	public function __construct(
		EntityFactoryInterface $entityFactory,
		ConfigInterface $config,
		IndexerRegistry $indexerRegistry
	) {
		$this->config = $config;
		$this->indexerRegistry = $indexerRegistry;
		parent::__construct($entityFactory);
	}

	/**
	 * @inheritdoc
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function loadData($printQuery = false, $logQuery = false) {
		if (!$this->isLoaded()) {
			foreach (array_keys($this->config->getIndexers()) as $indexerId) {
				$pos = strpos($indexerId, 'syncitgroup_sgindexer');
				if ($pos !== false) {

					$indexer = $this->indexerRegistry->get($indexerId);
					$item = $this->getNewEmptyItem();
					$data = [
						'indexer_id' => $indexer->getId(),
						'title' => $indexer->getTitle(),
						'description' => $indexer->getDescription(),
						'is_scheduled' => $indexer->isScheduled(),
						'status' => $indexer->getStatus(),
						'updated' => $indexer->getLatestUpdated(),
					];
					$this->addItem($item->setData($data));
				}
			}
			$this->_setIsLoaded(true);
		}
		return $this;
	}
}
