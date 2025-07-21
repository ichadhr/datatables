<?php

namespace Ichadhr\Datatables\Test;

use Ichadhr\Datatables\DB\SQLite;
use Ichadhr\Datatables\Datatables;
use PHPUnit\Framework\TestCase;
use Ichadhr\Datatables\Http\Request;

class DatatablesTestColumn extends DatatablesTestBase
{
    protected $db;
    protected $request;

    public function testSortsExcludingHiddenColumnsObjectData()
    {
        $this->request->query->set('search', ['value' => '']);
        $this->request->query->set('order', [['column' => '1', 'dir' => 'asc']]);

        $this->request->query->set('columns', [
            [
                'data' => 'name',
                'name' => '',
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => ''],
            ],
            ['data' => 'age', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
        ]);

        $this->db->query('Select id as fid, name, surname, age from mytable');
        $this->db->hide('fid');
        $this->db->hide('surname');
        $datatables = $this->db->generate()->toArray(); // only name and age visible
        // Debug output
        fwrite(STDERR, "testSortsExcludingHiddenColumnsObjectData: " . print_r($datatables, true) . "\n");
        $this->assertSame(['name' => 'Colin', 'age' => '19'], $datatables['data'][0]);
    }

    public function testReorderingColumnsDoesNotAffectOrdering()
    {
        $this->request->query->set('search', ['value' => '']);
        $this->request->query->set('order', [['column' => '0', 'dir' => 'asc']]);

        $this->request->query->set('columns', [
            ['data' => 'age', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            [
                'data' => 'surname',
                'name' => '',
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => ''],
            ],
            [
                'data' => 'name',
                'name' => '',
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => ''],
            ],
        ]);

        $this->db->query('Select id as fid, name, surname, age from mytable');
        $this->db->hide('fid');
        $datatables = $this->db->generate()->toArray();
        // Debug output
        fwrite(STDERR, "testReorderingColumnsDoesNotAffectOrdering: " . print_r($datatables, true) . "\n");
        $this->assertSame(['name' => 'Colin', 'surname' => 'McCoy', 'age' => '19'], $datatables['data'][0]);
    }

    public function testReorderingColumnsDoesNotAffectIndividualSearching()
    {
        $this->request->query->set('search', ['value' => '']);
        $this->request->query->set('order', [['column' => '0', 'dir' => 'asc']]);

        $this->request->query->set('columns', [
            [
                'data' => 'surname',
                'name' => '',
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => 'McCoy'],
            ],
            [
                'data' => 'age',
                'name' => '',
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '19'],
            ],
            [
                'data' => 'name',
                'name' => '',
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => 'Colin'],
            ],
        ]);

        $this->db->query('Select id as fid, name, surname, age from mytable');
        $this->db->hide('fid');
        $datatables = $this->db->generate()->toArray();
        // Debug output
        fwrite(STDERR, "testReorderingColumnsDoesNotAffectIndividualSearching: " . print_r($datatables, true) . "\n");
        $this->assertSame(['name' => 'Colin', 'surname' => 'McCoy', 'age' => '19'], $datatables['data'][0]);
    }
} 