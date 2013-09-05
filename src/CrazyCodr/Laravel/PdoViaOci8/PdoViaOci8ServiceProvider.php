<?php namespace CrazyCodr\Laravel\PdoViaOci8;

use Illuminate\Support\ServiceProvider;
use Config;

class PdoViaOci8ServiceProvider extends ServiceProvider {

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
    
        $this->package('crazycodr/laravel-pdo-via-oci8');
        
        // get the configs
        $tempConns = Config::get('laravel-pdo-via-oci8::database.connections');
        
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
                    $oConnector = new \CrazyCodr\Laravel\PdoViaOci8\Connectors\PdoViaOci8Connector();

                    $connection = $oConnector->connect($config);

                    return new \CrazyCodr\Laravel\PdoViaOci8\\PdoViaOci8Connection($connection, $config["database"], $config["prefix"]);
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