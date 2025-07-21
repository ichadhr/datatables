<?php


namespace Ichadhr\Datatables\DB;


use Ichadhr\Datatables\Column;
use Ichadhr\Datatables\Iterators\ColumnCollection;
use Ichadhr\Datatables\Query;

abstract class DBAdapter implements DatabaseInterface
{
    public $exactMatch = false;

    protected $debugInfo;

    /**
     * @return void
     */
    abstract public function connect();

    /**
     * @param Query $query
     * @return array
     */
    abstract public function query(Query $query);

    /**
     * @param Query $query
     * @return int
     */
    abstract public function count(Query $query);

    /**
     * @param $string
     * @param Query $query
     * @return string
     */
    abstract public function escape($string, Query $query);

    /**
     * @param string $query
     * @param ColumnCollection $columns
     * @return string
     */
    public function makeQueryString(string $query, ColumnCollection $columns): string
    {
        return 'SELECT `'.implode('`, `', $columns->names())."` FROM ($query)t";
    }

    /**
     * @param Query $query
     * @param string $column
     * @return string
     */
    public function makeDistinctQueryString(Query $query, string $column): string
    {
        return "SELECT $column FROM ($query)t GROUP BY $column";
    }

    /**
     * @param array $filter
     * @return string
     */
    public function makeWhereString(array $filter)
    {
        return ' WHERE '.implode(' AND ', $filter);
    }


    /**
     * @return bool
     */
    public function isExactMatch()
    {
        return $this->exactMatch;
    }

    /**
     * @param bool $value
     * @return void
     */
    public function setExactMatch(bool $value)
    {
        $this->exactMatch = $value;
    }

    /**
     * @param Query $query
     * @param Column $column
     * @param string $word
     * @return string
     */
    public function makeLikeString(Query $query, Column $column, string $word)
    {
        return $column->name.' LIKE '.$this->escape('%'.$word.'%', $query);
    }

     /**
     * @param Query $query
     * @param Column $column
     * @param string $word
     * @return string
     */
    public function makeEqualString(Query $query, Column $column, string $word)
    {
        return $column->name.' = '.$this->escape( $word, $query);
    }

    /**
     * @param array $o
     * @return string
     */
    public function makeOrderByString(array $o)
    {
        return ' ORDER BY '.implode(',', $o);
    }

    /**
     * @param $take
     * @param $skip
     * @return string
     */
    public function makeLimitString(int $take, int $skip)
    {
        return " LIMIT $take OFFSET $skip";
    }

    /**
     * @param $query
     * @return string
     */
    public function getQueryString($query): string
    {
        return $query;
    }

    public function setDebugInfo(?\Ichadhr\Datatables\DebugInfo $debugInfo = null)
    {
        $this->debugInfo = $debugInfo;
    }

    protected function logQueryDebug($sql, $params, $start, $result = null)
    {
        if ($this->debugInfo) {
            $this->debugInfo->logCall('db_query', [
                'sql' => $sql,
                'params' => $params,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                'row_count' => is_array($result) ? count($result) : null,
            ]);
        }
    }
}
