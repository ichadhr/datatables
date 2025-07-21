<?php namespace Ichadhr\Datatables\DB;

use Db;
use Ichadhr\Datatables\Query;

/**
 * Class PSAdapter
 * @package Ichadhr\Datatables\DB
 */
class PSAdapter extends DBAdapter
{
    /**
     * @var
     */
    protected $Db;
    /**
     * @var
     */
    protected $config;

    /**
     * PSAdapter constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @return $this
     */
    public function connect()
    {
        $this->Db = Db::getInstance();

        return $this;
    }

    /**
     * @param Query $query
     * @param bool $array
     * @param bool $user_cache
     * @return mixed
     */
    public function query(Query $query, $array = true, $user_cache = true)
    {
        $start = microtime(true);
        $result = $this->Db->executeS($query, $array, $user_cache);
        $this->logQueryDebug((string)$query, $query->escapes ?? [], $start, $result);
        return $result;
    }

    /**
     * @param Query $query
     * @return mixed
     */
    public function count(Query $query)
    {
        $data = $this->Db->getRow("Select count(*) as rowcount from ($query)t");

        return $data['rowcount'];
    }

    /**
     * @param $string
     * @param Query $query
     * @return string
     */
    public function escape($string, Query $query)
    {
        return "'" . pSQL($string) . "'";
    }
}
