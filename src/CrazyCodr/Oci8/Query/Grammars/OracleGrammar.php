<?php namespace CrazyCodr\Oci8\Query\Grammars;

use \Illuminate\Database\Query\Builder;

class OracleGrammar extends \Illuminate\Database\Query\Grammars\Grammar {

	/**
	 * The keyword identifier wrapper format.
	 *
	 * @var string
	 */
	protected $wrapper = '%s';

	/**
	 * Compile a select query into SQL.
	 *
	 * @param  \Illuminate\Database\Query\Builder
	 * @return string
	 */
	public function compileSelect(Builder $query)
	{
		$components = $this->compileComponents($query);

		// If an offset is present on the query, we will need to wrap the query in
		// a big "ANSI" offset syntax block. This is very nasty compared to the
		// other database systems but is necessary for implementing features.
		if ($query->limit > 0 OR $query->offset > 0)
		{
			return $this->compileAnsiOffset($query, $components);
		}

		return $this->concatenate($components);
	}

	/**
	 * Create a full ANSI offset clause for the query.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  array  $components
	 * @return string
	 */
	protected function compileAnsiOffset(Builder $query, $components)
	{
		$start = $query->offset + 1;

		$constraint = $this->compileRowConstraint($query);

		$sql = $this->concatenate($components);

		// We are now ready to build the final SQL query so we'll create a common table
		// expression from the query and get the records with row numbers within our
		// given limit and offset value that we just put on as a query constraint.
		$temp = $this->compileTableExpression($sql, $constraint, $query);
                
                return $temp;
	}

	/**
	 * Compile the limit / offset row constraint for a query.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @return string
	 */
	protected function compileRowConstraint($query)
	{
		$start = $query->offset + 1;

		if ($query->limit > 0)
		{
			$finish = $query->offset + $query->limit;

			return "between {$start} and {$finish}";
		}
	
		return ">= {$start}";
	}

	/**
	 * Compile a common table expression for a query.
	 *
	 * @param  string  $sql
	 * @param  string  $constraint
	 * @return string
         * 
 	 */
	protected function compileTableExpression($sql, $constraint, $query)
	{
            if ($query->limit > 0) {
                return "select t2.* from ( select t1.*, ROWNUM AS \"rn\" from ({$sql}) t1 ) t2 where t2.\"rn\" {$constraint}";
            } else {
                return "select * from ({$sql}) where rownum {$constraint}";
            }
	}

	/**
	 * Compile the "limit" portions of the query.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  int  $limit
	 * @return string
	 */
	protected function compileLimit(Builder $query, $limit)
	{
		return '';
	}

	/**
	 * Compile the "offset" portions of the query.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  int  $offset
	 * @return string
	 */
	protected function compileOffset(Builder $query, $offset)
	{
		return '';
	}
	
	/**
	* Compile a truncate table statement into SQL.
	*
	* @param  \Illuminate\Database\Query\Builder  $query
	* @return array
	*/
	public function compileTruncate(Builder $query)
	{
		return array('truncate table '.$this->wrapTable($query->from) => array());
	}

}
