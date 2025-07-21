<?php

namespace Ichadhr\Datatables\DB;

use Ichadhr\Datatables\Column;
use Ichadhr\Datatables\Iterators\ColumnCollection;
use Ichadhr\Datatables\Query;

interface DatabaseInterface
{
    public function __construct($config);

    /**
     * @return void
     */
    public function connect();

    /**
     * @param Query $query
     * @return array
     */
    public function query(Query $query);

    /**
     * @param Query $query
     * @return int
     */
    public function count(Query $query);

    /**
     * @param $string
     * @param Query $query
     * @return string
     */
    public function escape($string, Query $query);

    /**
     * @param string $query
     * @param ColumnCollection $columns
     * @return mixed
     */
    public function makeQueryString(string $query, ColumnCollection $columns);

    /**
     * @param Query $query
     * @param string $column
     * @return mixed
     */
    public function makeDistinctQueryString(Query $query, string $column);

    /**
     * @param array $filter
     * @return mixed
     */
    public function makeWhereString(array $filter);

    /**
     * @param bool $value
     * @return void
     */
    public function setExactMatch(bool $value);

    /**
     * @return mixed
     */
    public function isExactMatch();

    /**
     * @param Query $query
     * @param Column $column
     * @param string $word
     * @return mixed
     */
    public function makeLikeString(Query $query, Column $column, string $word);


    /**
     * @param Query $query
     * @param Column $column
     * @param string $word
     * @return mixed
     */
    public function makeEqualString(Query $query, Column $column, string $word);

    /**
     * @param array $o
     * @return mixed
     */
    public function makeOrderByString(array $o);

    /**
     * @param $take
     * @param $skip
     * @return mixed
     */
    public function makeLimitString(int $take, int $skip);

    /**
     * @param $query
     * @return string
     */
    public function getQueryString($query): string;

    // Add only the method signature for debug info
    public function setDebugInfo(?\Ichadhr\Datatables\DebugInfo $debugInfo = null);
}
