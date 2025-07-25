<?php

namespace Ichadhr\Datatables\DB;

use Ichadhr\Datatables\Column;
use Ichadhr\Datatables\Query;
use PDO;

/**
 * Class PGSQL // PostgreSql Adapter
 * @package Ichadhr\Datatables\DB
 */
class PGSQL extends DBAdapter
{
    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var array
     */
    protected $config;

    /**
     * MySQL constructor.
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
        $host = $this->config['host'];
        $port = $this->config['port'];
        $user = $this->config['username'];
        $pass = $this->config['password'];
        $database = $this->config['database'];

        $this->pdo = new PDO("pgsql:host=$host;dbname=$database;port=$port", "$user", "$pass");

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $this;
    }

    /**
     * @param Query $query
     * @return mixed
     */
    public function query(Query $query)
    {
        $start = microtime(true);
        $sql = $this->pdo->prepare($query);
        $sql->execute($query->escapes);
        $result = $sql->fetchAll(PDO::FETCH_ASSOC);

        $this->logQueryDebug((string)$query, $query->escapes, $start, $result);

        return $result;
    }

    /**
     * @param Query $query
     * @return mixed
     */
    public function count(Query $query)
    {
        $sql = $this->pdo->prepare("Select count(*) as rowcount from ($query)t");
        $sql->execute($query->escapes);

        return (int)$sql->fetchColumn();
    }

    /**
     * @param $string
     * @param Query $query
     * @return string
     */
    public function escape($string, Query $query)
    {
        $query->escapes[':binding_'.(count($query->escapes) + 1)] = $string;

        return ':binding_'.count($query->escapes);
    }


    /**
     * @param Query $query
     * @param Column $column
     * @param $word
     * @return string
     */
    public function makeLikeString(Query $query, Column $column, string $word)
    {
        return $column->name.'::varchar ILIKE '.$this->escape('%'.$word.'%', $query);
    }
}

