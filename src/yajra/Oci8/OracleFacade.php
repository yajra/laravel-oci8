<?php namespace yajra\Oci8;

use Illuminate\Support\Facades\Facade;

class OracleFacade extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor()
	{
		return 'oracle';
	}

}