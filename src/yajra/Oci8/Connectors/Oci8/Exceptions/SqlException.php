<?php
/**
 * SQL Exception
 *
 * @category Database
 * @package yajra/laravel-oci8
 * @author Arjay Angeles
 * @copyright Copyright (c) 2013 Arjay Angeles (http://github.com/yajra)
 * @license MIT
 */
namespace yajra\Oci8\Connectors\Oci8\Exceptions;

use Illuminate\Database;

class SqlException extends \PDOException
{
  /**
	 * The variable for error information.
	 *
	 * @var errorInfo
	 */
	public $errorInfo;
}
