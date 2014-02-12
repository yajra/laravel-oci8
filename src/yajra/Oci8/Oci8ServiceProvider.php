<?php namespace yajra\Oci8;

use Illuminate\Support\ServiceProvider;
use Config;

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
		$this->package('yajra/oci8');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{

		//Extend the connections with pdo-via-oci8 drivers by using a yajra\pdo\oci8 connector
		foreach(Config::get('database.connections') as $conn => $config)
		{

			//Only use configurations that feature a "pdo-via-oci8" or "oci8" or "oracle" driver
			if(!isset($config['driver']) || !in_array($config['driver'], array('pdo-via-oci8','oci8', 'oracle')) )
			{
				continue;
			}

			//Create a connector
	        $this->app['db']->extend($conn, function($config)
	        {
	            $connector = new Connectors\OracleConnector();
	            $connection = $connector->connect($config);
	            $db = new Oci8Connection($connection, $config["database"], $config["prefix"]);
	            // set oracle date format to match PHP's date
	            $db->setDateFormat('YYYY-MM-DD HH24:MI:SS');
	            return $db;
	        });

		}

	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}