<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal;

use Nextras\Dbal\Drivers\IDriver;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Result;


class Connection implements IConnection
{
	/** @var callable[]: function(Connection $connection) */
	public $onConnect = [];

	/** @var callable[]: function(Connection $connection) */
	public $onDisconnect = [];

	/** @var callable[]: function(Connection $connection, string $query, float $time, ?Result $result, ?DriverException $exception) */
	public $onQuery = [];

	/** @var array */
	private $config;

	/** @var IDriver */
	private $driver;

	/** @var IPlatform */
	private $platform;

	/** @var SqlProcessor */
	private $sqlPreprocessor;

	/** @var bool */
	private $connected;

	/** @var int */
	private $nestedTransactionIndex = 0;

	/** @var bool */
	private $nestedTransactionsWithSavepoint = true;


	/**
	 * @param  array $config see drivers for supported options
	 */
	public function __construct(array $config)
	{
		$this->config = $config;
		$this->driver = $this->createDriver();
		$this->sqlPreprocessor = $this->createSqlProcessor();
		$this->connected = $this->driver->isConnected();
	}


	/**
	 * Connects to a database.
	 * @return void
	 * @throws ConnectionException
	 */
	public function connect()
	{
		if ($this->connected) {
			return;
		}
		$this->driver->connect($this->config, function (string $sql, float $time, Result $result = null, DriverException $exception = null) {
			$this->fireEvent('onQuery', [$this, $sql, $time, $result, $exception]);
		});
		$this->connected = true;
		$this->nestedTransactionsWithSavepoint = (bool) ($this->config['nestedTransactionsWithSavepoint'] ?? true);
		$this->fireEvent('onConnect', [$this]);
	}


	/**
	 * Disconnects from a database.
	 * @return void
	 */
	public function disconnect()
	{
		if (!$this->connected) {
			return;
		}
		$this->driver->disconnect();
		$this->connected = false;
		$this->fireEvent('onDisconnect', [$this]);
	}


	/**
	 * Reconnects to a database.
	 * @return void
	 */
	public function reconnect()
	{
		$this->disconnect();
		$this->connect();
	}


	/**
	 * Reconnects to a database with new configration. Unchanged configuration is reused.
	 */
	public function reconnectWithConfig(array $config)
	{
		$this->disconnect();
		$this->config = $config + $this->config;
		$this->driver = $this->createDriver();
		$this->sqlPreprocessor = $this->createSqlProcessor();
		$this->connect();
	}


	public function getDriver(): IDriver
	{
		return $this->driver;
	}


	/**
	 * Returns connection configuration.
	 */
	public function getConfig(): array
	{
		return $this->config;
	}


	/** @inheritdoc */
	public function query(...$args)
	{
		$this->connected || $this->connect();
		$sql = $this->sqlPreprocessor->process($args);
		return $this->nativeQuery($sql);
	}


	/** @inheritdoc */
	public function queryArgs($query, array $args = [])
	{
		if (!is_array($query)) {
			array_unshift($args, $query);
		} else {
			$args = $query;
		}
		return call_user_func_array([$this, 'query'], $args);
	}


	public function queryByQueryBuilder(QueryBuilder $queryBuilder): Result
	{
		return $this->queryArgs($queryBuilder->getQuerySql(), $queryBuilder->getQueryParameters());
	}


	/** @inheritdoc */
	public function getLastInsertedId(string $sequenceName = null)
	{
		$this->connected || $this->connect();
		return $this->driver->getLastInsertedId($sequenceName);
	}


	/** @inheritdoc */
	public function getAffectedRows(): int
	{
		$this->connected || $this->connect();
		return $this->driver->getAffectedRows();
	}


	/** @inheritdoc */
	public function getPlatform(): IPlatform
	{
		if ($this->platform === null) {
			$this->platform = $this->driver->createPlatform($this);
		}

		return $this->platform;
	}


	/** @inheritdoc */
	public function createQueryBuilder(): QueryBuilder
	{
		return new QueryBuilder($this->driver);
	}


	public function setTransactionIsolationLevel(int $level)
	{
		$this->driver->setTransactionIsolationLevel($level);
	}


	/** @inheritdoc */
	public function transactional(callable $callback)
	{
		$this->beginTransaction();
		try {
			$returnValue = $callback($this);
			$this->commitTransaction();
			return $returnValue;

		} catch (\Exception $e) {
			$this->rollbackTransaction();
			throw $e;
		}
	}


	/** @inheritdoc */
	public function beginTransaction()
	{
		$this->connected || $this->connect();
		$this->nestedTransactionIndex++;
		if ($this->nestedTransactionIndex === 1) {
			$this->driver->beginTransaction();
		} elseif ($this->nestedTransactionsWithSavepoint) {
			$this->driver->createSavepoint($this->getSavepointName());
		}
	}


	/** @inheritdoc */
	public function commitTransaction()
	{
		if ($this->nestedTransactionIndex === 1) {
			$this->driver->commitTransaction();
		} elseif ($this->nestedTransactionsWithSavepoint) {
			$this->driver->releaseSavepoint($this->getSavepointName());
		}
		$this->nestedTransactionIndex--;
	}


	/** @inheritdoc */
	public function rollbackTransaction()
	{
		if ($this->nestedTransactionIndex === 1) {
			$this->driver->rollbackTransaction();
		} elseif ($this->nestedTransactionsWithSavepoint) {
			$this->driver->rollbackSavepoint($this->getSavepointName());
		}
		$this->nestedTransactionIndex--;
	}


	/** @inheritdoc */
	public function createSavepoint(string $name)
	{
		$this->driver->createSavepoint($name);
	}


	/** @inheritdoc */
	public function releaseSavepoint(string $name)
	{
		$this->driver->releaseSavepoint($name);
	}


	/** @inheritdoc */
	public function rollbackSavepoint(string $name)
	{
		$this->driver->rollbackSavepoint($name);
	}


	/** @inheritdoc */
	public function ping(): bool
	{
		if (!$this->connected) {
			return false;
		}
		return $this->driver->ping();
	}


	protected function getSavepointName(): string
	{
		return "NEXTRAS_SAVEPOINT_{$this->nestedTransactionIndex}";
	}


	private function nativeQuery(string $sql)
	{
		try {
			$result = $this->driver->query($sql);
			$this->fireEvent('onQuery', [
				$this,
				$sql,
				$this->driver->getQueryElapsedTime(),
				$result,
				null, // exception
			]);
			return $result;
		} catch (DriverException $exception) {
			$this->fireEvent('onQuery', [
				$this,
				$sql,
				$this->driver->getQueryElapsedTime(),
				null, // result
				$exception
			]);
			throw $exception;
		}
	}


	private function createDriver(): IDriver
	{
		if (empty($this->config['driver'])) {
			throw new InvalidStateException('Undefined driver. Choose from: mysqli, pgsql.');

		} elseif ($this->config['driver'] instanceof IDriver) {
			return $this->config['driver'];

		} else {
			$name = ucfirst($this->config['driver']);
			$class = "Nextras\\Dbal\\Drivers\\{$name}\\{$name}Driver";
			return new $class;
		}
	}


	private function createSqlProcessor(): SqlProcessor
	{
		if (isset($this->config['sqlProcessorFactory'])) {
			$factory = $this->config['sqlProcessorFactory'];
			assert($factory instanceof ISqlProcessorFactory);
			return $factory->create($this);
		} else {
			return new SqlProcessor($this->driver, $this->getPlatform());
		}
	}


	/**
	 * @return void
	 */
	private function fireEvent(string $event, array $args)
	{
		foreach ($this->$event as $callback) {
			call_user_func_array($callback, $args);
		}
	}
}
