<?php namespace yajra\Oci8\Schema;

use Closure;
use Illuminate\Support\Fluent;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Database\Schema\Blueprint;

class OracleBlueprint extends Blueprint {

	/**
	 * Create a default index name for the table.
	 *
	 * @param  string  $type
	 * @param  array   $columns
	 * @return string
	 */
	protected function createIndexName($type, array $columns)
	{
		// work around when working with db prefix
		// will result to same unique name
		$random = rand(0,99);

		// create index name
		$index = strtolower($this->table.'_'.implode('_', $columns).'_'.$type);

		// max index name length is 30 chars
		return substr(str_replace(array('-', '.'), '_', $index), 0, 28) . $random;
	}

}
