<?php namespace yajra\Oci8;

use Config;
use Illuminate\Support\ServiceProvider;
use yajra\Oci8\Connectors\OracleConnector as Connector;

class Oci8ServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('yajra/laravel-oci8');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		//Extend the connections with pdo-via-oci8 drivers by using a yajra\pdo\oci8 connector
		foreach (Config::get('database.connections') as $conn => $config)
		{
			//Only use configurations that feature a "pdo-via-oci8" or "oci8" or "oracle" driver
			if ( ! isset($config['driver']) || ! in_array($config['driver'], ['pdo-via-oci8', 'oci8', 'oracle']))
			{
				continue;
			}

			$this->app->resolving('db', function ($db)
			{
				$db->extend('oracle', function ($config)
				{
					$connector = new Connector();
					$connection = $connector->connect($config);
					$db = new Oci8Connection($connection, $config["database"], $config["prefix"]);
					// set oracle date format to match PHP date
					$db->setDateFormat('YYYY-MM-DD HH24:MI:SS');

					// Like Postgres, Oracle allows the concept of "schema"
					if (isset($config['schema']))
					{
						$db->setSchema($config['schema']);
					}

					return $db;
				});
			});
		}
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return string[]
	 */
	public function provides()
	{
		return [];
	}

}
