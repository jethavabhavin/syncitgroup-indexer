<?php
/**
 * @category  Syncit Group Elastic Search Indexer
 * @package   SyncitGroup_Indexer
 * @copyright Copyright (c) 2021 Bhavin
 * @author    Bhavin
 */
declare (strict_types = 1);

namespace SyncitGroup\Indexer\Block\Adminhtml\System\Config;

/**
 * Elasticsearch 7.x test connection block
 */
class CheckStatus extends \Magento\AdvancedSearch\Block\Adminhtml\System\Config\TestConnection {

	/**
	 * Set template to itself
	 *
	 * @return $this
	 * @since 100.1.0
	 */
	protected function _prepareLayout() {
		parent::_prepareLayout();
		$this->setTemplate('SyncitGroup_Indexer::system/config/checkstatus.phtml');
		return $this;
	}

	/**
	 * Get the button and scripts contents
	 *
	 * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
	 * @return string
	 * @since 100.1.0
	 */
	protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element) {
		$originalData = $element->getOriginalData();
		$this->addData(
			[
				'button_label' => __($originalData['button_label']),
				'html_id' => $element->getHtmlId(),
				'ajax_url' => $this->_urlBuilder->getUrl('sgindexer/elasticsearch/checkstatus'),
				'field_mapping' => str_replace('"', '\\"', json_encode($this->_getFieldMapping())),
			]
		);

		return $this->_toHtml();
	}
	/**
	 * @inheritdoc
	 */
	protected function _getFieldMapping(): array
	{
		$fields = [
			'hostname' => 'sgelasticsearchindexer_indexer_elasticsearch7_server_hostname',
			'port' => 'sgelasticsearchindexer_indexer_elasticsearch7_server_port',
			'index' => 'sgelasticsearchindexer_indexer_elasticsearch7_index_prefix',
		];

		return array_merge(parent::_getFieldMapping(), $fields);
	}
}
