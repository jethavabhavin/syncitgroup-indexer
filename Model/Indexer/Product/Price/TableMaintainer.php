<?php
/**
 * @category  Syncit Group Elastic Search Indexer
 * @package   SyncitGroup_Indexer
 * @copyright Copyright (c) 2021 Bhavin
 * @author    Bhavin
 */
declare (strict_types = 1);

namespace SyncitGroup\Indexer\Model\Indexer\Product\Price;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Search\Request\Dimension;

/**
 * Class encapsulate logic of work with tables per store in Product Price indexer
 */
class TableMaintainer extends \Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer {
	/**
	 * Catalog product price index table name
	 */
	const MAIN_INDEX_TABLE = 'syncitgroup_price';
	/**
	 * @var ResourceConnection
	 */
	private $resource;

	/**
	 * @var AdapterInterface
	 */
	private $connection;

	/**
	 * @var null|string
	 */
	private $connectionName;

	/**
	 * @param ResourceConnection $resource
	 * @param null $connectionName
	 */
	public function __construct(
		ResourceConnection $resource,
		$connectionName = null
	) {
		$this->resource = $resource;
		$this->connectionName = $connectionName;
	}

	/**
	 * Get connection for work with price indexer
	 *
	 * @return AdapterInterface
	 */
	public function getConnection(): AdapterInterface {
		if (null === $this->connection) {
			$this->connection = $this->resource->getConnection($this->connectionName);
		}
		return $this->connection;
	}

	/**
	 * Return validated table name
	 *
	 * @param string $table
	 * @return string
	 */
	private function getTable(string $table): string {
		return $this->resource->getTableName($table);
	}

	/**
	 * Create table based on main table
	 *
	 * @param string $mainTableName
	 * @param string $newTableName
	 *
	 * @return void
	 *
	 * @throws \Zend_Db_Exception
	 */
	private function createTable(string $mainTableName, string $newTableName) {
		if (!$this->getConnection()->isTableExists($newTableName)) {
			$this->getConnection()->createTable(
				$this->getConnection()->createTableByDdl($mainTableName, $newTableName)
			);
		}
	}

	/**
	 * Truncate table
	 *
	 * @param string $tableName
	 *
	 * @return void
	 */
	private function truncateTable(string $tableName) {
		if ($this->getConnection()->isTableExists($tableName)) {
			$this->getConnection()->truncateTable($tableName);
		}
	}

	/**
	 * Return main index table name
	 *
	 * @param Dimension[] $dimensions
	 *
	 * @return string
	 */
	public function getMainTable(array $dimensions): string {
		return $this->getTable(self::MAIN_INDEX_TABLE . "_" . $dimensions['ws']->getValue());
	}
	/**
	 * Create main and replica index tables for dimensions
	 *
	 * @param Dimension[] $dimensions
	 *
	 * @return void
	 *
	 * @throws \Zend_Db_Exception
	 */
	public function createTablesForDimensions(array $dimensions) {
		$mainTableName = $this->getTable(self::MAIN_INDEX_TABLE);

		$mainReplicaTableName = $this->getMainTable($dimensions);
		//Create replica table for dimensions based on main replica table
		$this->createTable(
			$mainTableName,
			$mainReplicaTableName
		);
	}
}
