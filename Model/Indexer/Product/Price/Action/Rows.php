<?php
/**
 * @category  Syncit Group Elastic Search Indexer
 * @package   SyncitGroup_Indexer
 * @copyright Copyright (c) 2021 Bhavin
 * @author    Bhavin
 */
namespace SyncitGroup\Indexer\Model\Indexer\Product\Price\Action;

/**
 * Class Rows reindex action for mass actions
 *
 */
class Rows extends Full {
	/**
	 * Execute Rows reindex
	 *
	 * @param array $ids
	 * @return void
	 * @throws \Magento\Framework\Exception\InputException
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
	public function execute($ids = null): void {
		if (empty($ids)) {
			throw new \Magento\Framework\Exception\InputException(__('Bad value was supplied.'));
		}
		try {
			$this->_reindexRows($ids);
		} catch (\Exception $e) {
			throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()), $e);
		}
	}
}
