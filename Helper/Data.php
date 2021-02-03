<?php
/**
 * @category  Syncit Group Elastic Search Indexer
 * @package   SyncitGroup_Indexer
 * @copyright Copyright (c) 2021 Bhavin
 * @author    Bhavin
 */
namespace SyncitGroup\Indexer\Helper;

use Magento\AdvancedSearch\Model\Client\ClientResolver;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Search\EngineResolverInterface;
use Magento\Store\Model\ScopeInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper {
	/**
	 * @var \Magento\Framework\App\Config\ScopeConfigInterfac
	 */
	protected $_scopeConfig;

	/*Extention Enable Disable Constant*/
	CONST ENABLE = 'sgelasticsearchindexer/indexer/enable';
	CONST CONF_HOSTNAME = 'sgelasticsearchindexer/indexer/elasticsearch7_server_hostname';
	CONST CONF_PORTNUMBER = 'sgelasticsearchindexer/indexer/elasticsearch7_server_port';
	CONST CONF_PREFIX = 'sgelasticsearchindexer/indexer/elasticsearch7_index_prefix';

	/**
	 * Search engine name
	 */
	const ENGINE_NAME = 'elasticsearch';

	/**
	 * Elasticsearch Entity type
	 */
	const ELASTICSEARCH_TYPE_DOCUMENT = 'document';

	/**
	 * Elasticsearch default Entity type
	 */
	const ELASTICSEARCH_TYPE_DEFAULT = 'product';

	/**
	 * Default Elasticsearch server timeout
	 */
	const ELASTICSEARCH_DEFAULT_TIMEOUT = 15;

	/**
	 * @var string
	 */
	private $prefix;

	/**
	 * @var ClientResolver
	 */
	private $clientResolver;

	/**
	 * @var EngineResolverInterface
	 */
	private $engineResolver;

	/**
	 * Available Elasticsearch engines.
	 *
	 * @var array
	 */
	private $engineList;
	/**
	 * @param Context $context
	 * @param ClientResolver $clientResolver
	 * @param EngineResolverInterface $engineResolver
	 * @param $prefix
	 * @param array $engineList
	 */
	public function __construct(
		Context $context,
		ClientResolver $clientResolver,
		EngineResolverInterface $engineResolver,
		$prefix = null,
		$engineList = []
	) {
		$this->_scopeConfig = $context->getScopeConfig();
	}

	/**
	 * @inheritdoc
	 *
	 * @since 100.1.0
	 */
	public function prepareClientOptions($options = []) {
		$defaultOptions = [
			'hostname' => $this->getHostname(),
			'port' => $this->getPortnumber(),
			'index' => $this->getPrefix(),
			'enableAuth' => 0,
			'username' => '',
			'password' => '',
			'timeout' => self::ELASTICSEARCH_DEFAULT_TIMEOUT,
		];
		$options = array_merge($defaultOptions, $options);
		$allowedOptions = array_merge(array_keys($defaultOptions), ['engine']);

		return array_filter(
			$options,
			function (string $key) use ($allowedOptions) {
				return in_array($key, $allowedOptions);
			},
			ARRAY_FILTER_USE_KEY
		);
	}
	/**
	 * Get Elasticsearch entity type
	 *
	 * @return string
	 * @since 100.1.0
	 */
	public function getEntityType() {
		return self::ELASTICSEARCH_TYPE_DOCUMENT;
	}
	/**
	 * Retrieve extention enable or disable
	 *
	 * @return boolean
	 */
	public function isExtentionEnable() {
		return $this->_scopeConfig->getValue(Self::ENABLE, ScopeInterface::SCOPE_STORE);
	}
	/**
	 * Retrieve is email send enable or not
	 *
	 * @return boolean
	 */
	public function getHostname($store = null) {
		if ($store) {
			$data = $store->getConfig(Self::CONF_HOSTNAME);
		} else {
			$data = $this->_scopeConfig->getValue(Self::CONF_HOSTNAME, ScopeInterface::SCOPE_STORE);
		}
		return $data;
	}
	/**
	 * Retrieve email subject
	 *
	 * @return string
	 */
	public function getPortnumber($store = null) {
		if ($store) {
			$data = $store->getConfig(Self::CONF_PORTNUMBER);
		} else {
			$data = $this->_scopeConfig->getValue(Self::CONF_PORTNUMBER, ScopeInterface::SCOPE_STORE);
		}
		return $data;
	}
	/**
	 * Retrieve email html template
	 *
	 * @return string
	 */
	public function getPrefix($store = null) {
		if ($store) {
			$data = $store->getConfig(Self::CONF_PREFIX);
		} else {
			$data = $this->_scopeConfig->getValue(Self::CONF_PREFIX, ScopeInterface::SCOPE_STORE);
		}
		return $data;
	}
}