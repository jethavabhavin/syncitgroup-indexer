<?php
/**
 * @category  Syncit Group Elastic Search Indexer
 * @package   SyncitGroup_Indexer
 * @copyright Copyright (c) 2021 Bhavin
 * @author    Bhavin
 */
namespace SyncitGroup\Indexer\Controller\Adminhtml\Elasticsearch;

use Magento\AdvancedSearch\Model\Client\ClientResolver;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filter\StripTags;
use Magento\Framework\HTTP\Client\Curl;

class Checkstatus extends Action {
	/**
	 * Authorization level of a basic admin session.
	 *
	 * @see _isAllowed()
	 */
	// const ADMIN_RESOURCE = 'SyncitGroup_Indexer::sgindexerconfig';

	/**
	 * @var ClientResolver
	 */
	private $clientResolver;

	/**
	 * @var JsonFactory
	 */
	private $resultJsonFactory;

	/**
	 * @var StripTags
	 */
	private $tagFilter;

	/**
	 * @param Context           $context
	 * @param ClientResolver    $clientResolver
	 * @param JsonFactory       $resultJsonFactory
	 * @param StripTags         $tagFilter
	 */
	public function __construct(
		Context $context,
		ClientResolver $clientResolver,
		JsonFactory $resultJsonFactory,
		StripTags $tagFilter,
		Curl $curl
	) {
		parent::__construct($context);
		$this->clientResolver = $clientResolver;
		$this->resultJsonFactory = $resultJsonFactory;
		$this->tagFilter = $tagFilter;
		$this->curl = $curl;
	}

	/**
	 * Check for connection to server
	 *
	 * @return \Magento\Framework\Controller\Result\Json
	 */
	public function execute() {
		$result = [
			'success' => false,
			'errorMessage' => '',
		];
		$options = $this->getRequest()->getParams();
		try {
			$response = $this->clientResolver->create("elasticsearch7", $options)->testConnection();
			if ($response) {
				$result['success'] = true;
				$url = "{$options['hostname']}:{$options['port']}";
				$this->curl->get($url);
				$result['html'] = $this->curl->getBody();
			}
		} catch (\Magento\Framework\Exception\LocalizedException $e) {
			$result['errorMessage'] = $e->getMessage();
		} catch (\Exception $e) {
			$message = __($e->getMessage());
			$result['errorMessage'] = $this->tagFilter->filter($message);
		}

		/** @var \Magento\Framework\Controller\Result\Json $resultJson */
		$resultJson = $this->resultJsonFactory->create();
		return $resultJson->setData($result);
	}
}
