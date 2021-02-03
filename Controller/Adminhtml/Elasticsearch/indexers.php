<?php
/**
 * @category  Syncit Group Elastic Search Indexer
 * @package   SyncitGroup_Indexer
 * @copyright Copyright (c) 2021 Bhavin
 * @author    Bhavin
 */
namespace SyncitGroup\Indexer\Controller\Adminhtml\Elasticsearch;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class indexers extends \Magento\Backend\App\Action {
	/**
	 * Authorization level of a basic admin session
	 *
	 * @see _isAllowed()
	 */
	const ADMIN_RESOURCE = 'SyncitGroup_SgForm::sgindexer';
	protected $resultPageFactory;

	/**
	 * Constructor
	 *
	 * @param \Magento\Backend\App\Action\Context  $context
	 * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
	 */
	public function __construct(
		\Magento\Backend\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory
	) {
		$this->resultPageFactory = $resultPageFactory;
		parent::__construct($context);
	}

	/**
	 * Execute view action
	 *
	 * @return \Magento\Framework\Controller\ResultInterface
	 */
	public function execute() {
		return $this->resultPageFactory->create();
	}
}
