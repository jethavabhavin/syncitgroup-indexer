<?php
/**
 * @category  Syncit Group Elastic Search Indexer
 * @package   SyncitGroup_Indexer
 * @copyright Copyright (c) 2021 Bhavin
 * @author    Bhavin
 */
namespace SyncitGroup\Indexer\Model\Indexer\Product\Price\Action;

/**
 * Class Row reindex action
 *
 */
class Row extends Full {
	/**
	 * Execute Row reindex
	 *
	 * @param int|null $id
	 * @return void
	 * @throws \Magento\Framework\Exception\InputException
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
	public function execute($id = null): void {
		if (!isset($id) || empty($id)) {
			throw new \Magento\Framework\Exception\InputException(
				__('We can\'t rebuild the index for an undefined product.')
			);
		}
		try {
			$this->_reindexRows([$id]);
		} catch (\Exception $e) {
			throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()), $e);
		}
	}
}
