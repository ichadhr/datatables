<?php

namespace Ichadhr\Datatables\Test;

use Ichadhr\Datatables\DB\SQLite;
use Ichadhr\Datatables\Datatables;
use PHPUnit\Framework\TestCase;
use Ichadhr\Datatables\Http\Request;

class DatatablesTestIndex extends DatatablesTestBase
{

    protected $db;
    protected $request;

    // Removed tests that do not use the columns array; see DatatablesTestCore.php

    public function testFiltersDataViaGlobalSearch()
    {
        $this->request->query->set('search', ['value' => 'doe']);

        $this->request->query->set('columns', [
            ['data' => '0', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => '1', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
        ]);

        $this->db->query('Select name, surname from mytable');
        $datatables = $this->db->generate()->toArray();
        $this->assertSame(11, $datatables['recordsTotal']);
        $this->assertSame(2, $datatables['recordsFiltered']);
    }

    public function testSortsDataViaSorting()
    {
        $this->request->query->set('search', ['value' => '']);
        $this->request->query->set('order', [['column' => '1', 'dir' => 'desc']]); //surname-desc

        $this->request->query->set('columns', [
            ['data' => '0', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => '1', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => '2', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
        ]);

        $this->db->query('Select name, surname, age from mytable');
        $datatables = $this->db->generate()->toArray();
        // Debug output
        fwrite(STDERR, "testSortsDataViaSorting: " . print_r($datatables, true) . "\n");
        $this->assertSame(['Todd', 'Wycoff', '36'], $datatables['data'][0]);
    }

    public function testSortsExcludingHiddenColumns()
    {
        $this->request->query->set('search', ['value' => '']);
        $this->request->query->set('order', [['column' => '1', 'dir' => 'asc']]); // age - asc

        $this->request->query->set('columns', [
            ['data' => '0', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => '1', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
        ]);

        $this->db->query('Select id as fid, name, surname, age from mytable');
        $this->db->hide('fid');
        $this->db->hide('surname');
        $datatables = $this->db->generate()->toArray(); // only name and age visible
        // Debug output
        fwrite(STDERR, "testSortsExcludingHiddenColumns: " . print_r($datatables, true) . "\n");
        $this->assertSame(['Colin', '19'], $datatables['data'][0]);
    }

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

    public function testReorderingColumnsDoesNotAffectGlobalSearching()
    {
        $this->request->query->set('search', ['value' => 'Stephanie']);
        $this->request->query->set('order', [['column' => '0', 'dir' => 'asc']]);

        $this->request->query->set('columns', [
            ['data' => '2', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => '0', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => '1', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
        ]);

        $this->db->query('Select id as fid, name, surname, age from mytable');
        $this->db->hide('fid');
        $datatables = $this->db->generate()->toArray();
        // Debug output
        fwrite(STDERR, "testReorderingColumnsDoesNotAffectGlobalSearching: " . print_r($datatables, true) . "\n");
        $this->assertSame(['Stephanie', 'Skinner', '45'], $datatables['data'][0]);
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

    public function testCustomFilteringBetween()
    {
        $this->request->query->set('search', ['value' => '']);
        $this->request->query->set('order', [['column' => '0', 'dir' => 'asc']]);

        $this->request->query->set('columns', [
            ['data' => '0', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => '1', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => '2', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
        ]);

        $this->db->query('Select id as fid, name, surname, age from mytable');
        $this->db->filter('fid', function () {
            return $this->between(4, 6);
        });

        $datatables = $this->db->generate()->toArray();
        // Debug output
        fwrite(STDERR, "testCustomFilteringBetween: " . print_r($datatables, true) . "\n");
        $this->assertSame(11, $datatables['recordsTotal']);
        $this->assertSame(3, $datatables['recordsFiltered']);
    }

    public function testCustomFilteringWhereIn()
    {
        $this->request->query->set('search', ['value' => '']);
        $this->request->query->set('order', [['column' => '0', 'dir' => 'asc']]);

        $this->request->query->set('columns', [
            ['data' => '0', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => '1', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => '2', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
        ]);

        $this->db->query('Select id as fid, name, surname, age from mytable');
        $this->db->filter('fid', function () {
            return $this->whereIn([5]);
        });

        $datatables = $this->db->generate()->toArray();
        // Debug output
        fwrite(STDERR, "testCustomFilteringWhereIn: " . print_r($datatables, true) . "\n");
        $this->assertSame(11, $datatables['recordsTotal']);
        $this->assertSame(1, $datatables['recordsFiltered']);
        $this->assertSame(['5', 'Ruby', 'Pickett', '28'], $datatables['data'][0]);
    }

    public function testReturnDefaultSearchWhenNull()
    {
        $this->request->query->set('search', ['value' => '']);
        $this->request->query->set('order', [['column' => '0', 'dir' => 'asc']]);

        $this->request->query->set('columns', [
            ['data' => '0', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '5']],
            ['data' => '1', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => '2', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
        ]);

        $this->db->query('Select id as fid, name, surname, age from mytable');
        $this->db->filter('fid', function () {
            //return $this->defaultFilter(); // when it is not defined, returns defaultFilter
        });

        $datatables = $this->db->generate()->toArray();
        // Debug output
        fwrite(STDERR, "testReturnDefaultSearchWhenNull: " . print_r($datatables, true) . "\n");
        $this->assertSame(11, $datatables['recordsTotal']);
        $this->assertSame(1, $datatables['recordsFiltered']);
        $this->assertSame(['5', 'Ruby', 'Pickett', '28'], $datatables['data'][0]);
    }
}
