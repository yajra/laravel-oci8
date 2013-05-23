<?php namespace Jfelder\OracleDB;

use Illuminate\Support\ServiceProvider;
use Config;

class OracledbServiceProvider extends ServiceProvider {

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
                
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
            $this->package('jfelder/oracledb');
            
            // get the configs
            $tempConns = Config::get('oracledb::database.connections');
            
            // Add my database configurations to the default set of configurations                        
            $this->app['config']['database.connections'] = array_merge(
                $this->app['config']['database.connections']
                ,$tempConns
            );
            
            $aConnKeys = array_keys($tempConns);
            
            if (is_array($aConnKeys))
            {
                foreach ($aConnKeys as $conn)
                {
                    $this->app['db']->extend($conn, function($config) 
                    {
                        $oConnector = new \Jfelder\OracleDB\Connectors\OracleConnector();

                        $connection = $oConnector->connect($config);

                        return new \Jfelder\OracleDB\OracleConnection($connection, $config["database"], $config["prefix"]);
                    });
                }
            }
            else
            {
                throw new \ErrorException('Configuration File is corrupt.');
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