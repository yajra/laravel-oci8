<?php namespace CrazyCodr\Oci8;

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
		$this->package('crazycodr/oci8');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{

		//Extend the connections with pdo-via-oci8 drivers by using a crazycodr\pdo\oci8 connector
		foreach(Config::get('database.connections') as $conn => $config)
		{

			//Only use configurations that feature a "pdo-via-oci8" driver
			if(!isset($config['driver']) || $config['driver'] != 'pdo-via-oci8')
			{
				continue;
			}

			//Create a connector
	        $this->app['db']->extend($conn, function($config) 
	        {
	            $oConnector = new \CrazyCodr\Oci8\Connectors\Oci8Connector();
	            $connection = $oConnector->connect($config);
	            return new \CrazyCodr\Oci8\Oci8Connection($connection, $config["database"], $config["prefix"]);
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