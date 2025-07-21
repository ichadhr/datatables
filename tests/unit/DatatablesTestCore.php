<?php

namespace Ichadhr\Datatables\Test;

use Ichadhr\Datatables\DB\SQLite;
use Ichadhr\Datatables\Datatables;
use PHPUnit\Framework\TestCase;
use Ichadhr\Datatables\Http\Request;

class DatatablesTestCore extends DatatablesTestBase
{
    protected $db;
    protected $request;

    private function customfunction($data)
    {
        return substr($data, 0, 3).'...';
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(Datatables::class, $this->db);
    }

    public function testReturnsRecordCounts()
    {
        $this->db->query('select id as fid, name, surname, age from mytable where id > 3');
        $datatables = $this->db->generate()->toArray();

        $this->assertSame(8, $datatables['recordsTotal']);
        $this->assertSame(8, $datatables['recordsFiltered']);
    }

    public function testReturnsRecordCountsThatSetWithoutRunningAnotherQuery()
    {
        $this->db->query('select id as fid, name, surname, age from mytable where id > 3');

        $datatables = $this->db->generate()->toArray();

        $this->assertSame(2, count($this->db->queries()));
        $this->assertSame(8, $datatables['recordsTotal']);
        $this->assertSame(8, $datatables['recordsFiltered']);

        $this->db->setTotalRecords(8);
        $datatables = $this->db->generate()->toArray();

        $this->assertSame(1, count($this->db->queries()));
        $this->assertSame(8, $datatables['recordsTotal']);
        $this->assertSame(8, $datatables['recordsFiltered']);
    }

    public function testReturnsDataFromABasicSql()
    {
        $this->db->query('select id as fid, name, surname, age from mytable');

        $data = $this->db->generate()->toArray()['data'][0];

        $this->assertSame("1", $data[0]);
        $this->assertSame("John", $data[1]);
        $this->assertStringContainsString('Doe', $data[2]);
    }

    public function testSetsColumnNamesFromAliases()
    {
        $this->db->query("select
                  film_id as fid,
                  title,
                  'description' as info,
                  release_year 'r_year',
                  film.rental_rate,
                  film.length as mins
            from film");

        $this->assertSame(['fid', 'title', 'info', 'r_year', 'rental_rate', 'mins'], $this->db->getColumns());
    }

    public function testHidesUnnecessaryColumnsFromOutput()
    {
        $this->db->query('select id as fid, name, surname, age from mytable');
        $this->db->hide('fid');
        $data = $this->db->generate()->toArray()['data']['2'];

        $this->assertCount(3, $data);
        $this->assertSame(['name', 'surname', 'age'], $this->db->getColumns());
    }

    public function testReturnsModifiedDataViaClosureFunction()
    {
        $this->db->query('select id as fid, name, surname, age from mytable');

        $this->db->edit('name', function ($data) {
            return strtolower($data['name']);
        });

        $this->db->edit('surname', function ($data) {
            return $this->customfunction($data['surname']);
        });

        $data = $this->db->generate()->toArray()['data']['2'];

        $this->assertSame('george', $data[1]);
        $this->assertSame('Mar...', $data[2]);
    }

    public function testReturnsUsingSpecialMysqlWordsIfItIsEscaped()
    {
        $this->db->query('select name, surname as `default`, age from mytable');

        $this->db->edit('default', function ($data) {
            return $this->customfunction($data['default']);
        });

        $data = $this->db->generate()->toArray()['data']['2'];

        $this->assertSame('Mar...', $data[1]);
    }

    public function testReturnsColumnNamesFromQueryThatIncludesASubqueryInSelectStatement()
    {
        $dt = $this->db->query("SELECT column_name,
            (SELECT group_concat(cp.GRANTEE)
            FROM COLUMN_PRIVILEGES cp
            WHERE cp.TABLE_SCHEMA = COLUMNS.TABLE_SCHEMA
            AND cp.TABLE_NAME = COLUMNS.TABLE_NAME
            AND cp.COLUMN_NAME = COLUMNS.COLUMN_NAME)
            privs
            FROM COLUMNS
            WHERE table_schema = 'mysql' AND table_name = 'user';");

        $this->assertSame(['column_name', 'privs'], $dt->getColumns());
    }

    public function testReturnsColumnNamesFromQueryThatIncludesASubqueryInWhereStatement()
    {
        $dt = $this->db->query("SELECT column_name
            FROM COLUMNS
            WHERE table_schema = 'mysql' AND table_name = 'user'
            and (SELECT group_concat(cp.GRANTEE)
            FROM COLUMN_PRIVILEGES cp
            WHERE cp.TABLE_SCHEMA = COLUMNS.TABLE_SCHEMA
            AND cp.TABLE_NAME = COLUMNS.TABLE_NAME
            AND cp.COLUMN_NAME = COLUMNS.COLUMN_NAME) is not null;");
        $columns = $dt->getColumns();

        $this->assertSame($columns[0], 'column_name');
    }
} 