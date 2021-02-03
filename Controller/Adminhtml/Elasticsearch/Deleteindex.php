<?php
/**
 * @category  Syncit Group Elastic Search Indexer
 * @package   SyncitGroup_Indexer
 * @copyright Copyright (c) 2021 Bhavin
 * @author    Bhavin
 */
namespace SyncitGroup\Indexer\Controller\Adminhtml\Elasticsearch;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Mageplaza\Smtp\Model\LogFactory;
use SyncitGroup\Indexer\Model\Price;

class Deleteindex extends Action {

	/**
	 * @var mixed
	 */
	protected $priceIndexer;

	/**
	 * Delete constructor.
	 *
	 * @param Action\Context $context
	 * @param LogFactory $logFactory
	 */
	public function __construct(
		Action\Context $context,
		Price $priceIndexer
	) {
		parent::__construct($context);

		$this->priceIndexer = $priceIndexer;
	}

	/**
	 * @return $this|ResponseInterface|ResultInterface
	 */
	public function execute() {
		/** @var Redirect $resultRedirect */
		$resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
		$this->priceIndexer->deleteIndexes();
		return $resultRedirect->setPath('*/*/indexers');
	}
}
