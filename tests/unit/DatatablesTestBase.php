<?php

namespace Ichadhr\Datatables\Test;

use Ichadhr\Datatables\DB\SQLite;
use Ichadhr\Datatables\Datatables;
use PHPUnit\Framework\TestCase;
use Ichadhr\Datatables\Http\Request;

abstract class DatatablesTestBase extends TestCase
{
    protected $db;
    protected $request;

    public function setUp(): void
    {
        $sqlconfig = __DIR__.'/../fixtures/test.db';
        $this->request = Request::create(array(), ['draw' => 1]);
        $this->db = new Datatables(new SQLite($sqlconfig), $this->request);
    }

    public function tearDown(): void
    {
        unset($this->db);
    }
} 