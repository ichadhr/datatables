<?php

namespace Ichadhr\Datatables;

use Closure;
use Ichadhr\Datatables\DB\DatabaseInterface;
use Ichadhr\Datatables\Http\Request;
use Ichadhr\Datatables\Iterators\ColumnCollection;
use Ichadhr\Datatables\DebugInfo;

/**
 * Class Datatables
 *
 * @package Ichadhr\Datatables
 */
class Datatables
{
    /**
     * @var DatabaseInterface
     */
    protected $db;

    /**
     * @var ColumnCollection
     */
    protected $columns;

    /**
     * @var QueryBuilder
     */
    protected $builder;

    /**
     * @var Option
     */
    protected $options;

    /**
     * Custom escapes
     * @var array
     */
    public $escapes = [];

    /**
     * @var array
     */
    protected $response;

    /**
     * @var array
     */
    protected $distinctColumn = [];

    /**
     * @var array
     */
    protected $distinctData = [];

    /**
     * @var array
     */
    private $queries = [];

    /**
     * @var int
     */
    private $recordsTotal;

    protected $debug = false;
    public $debugInfo;

    /**
     * Datatables constructor.
     *
     * @param DatabaseInterface $db
     * @param Request|null $request
     */
    public function __construct(DatabaseInterface $db, ?Request $request = null)
    {
        $this->db = $db->connect();
        $this->options = new Option($request ?: Request::createFromGlobals());
    }

    /**
     * @param $column
     * @param Closure $closure
     * @return Datatables
     */
    public function add($column, Closure $closure): Datatables
    {
        $column = new Column($column);
        $column->closure = $closure;
        $column->interaction = false;
        $this->columns->append($column);

        return $this;
    }

    /**
     * @param $column
     * @param Closure $closure
     * @return Datatables
     */
    public function edit($column, Closure $closure): Datatables
    {
        $column = $this->columns->getByName($column);
        $column->closure = $closure;

        return $this;
    }

    /**
     * @param $column
     * @param Closure $closure
     * @return Datatables
     */
    public function filter($column, Closure $closure, $filterType = CustomFilterType::INDIVIDUAL): Datatables
    {
        $column = $this->columns->getByName($column);
        $column->customIndividualFilter = $closure;

        $column->customFilterType = $filterType;
        if ($filterType !== CustomFilterType::GLOBALLY) {
            $column->customIndividualFilter = $closure;
        }
        if ($filterType !== CustomFilterType::INDIVIDUAL) {
            $column->customGlobalFilter = $closure;
        }

        return $this;
    }

    /**
     * @param string $key
     * @param string $value
     * @return Datatables
     */
    public function escape($key, $value): Datatables
    {
        $this->escapes[$key] = $value;

        return $this;
    }

    /**
     * @param bool $value
     * @return Datatables
     */
    public function forceExactMatch(bool $value): Datatables
    {
        $this->db->setExactMatch($value);

        return $this;
    }

    /**
     * @param $name
     * @return Datatables
     */
    public function setDistinctResponseFrom($name): Datatables
    {
        $this->distinctColumn[] = $name;

        return $this;
    }

    /**
     * @param $array
     * @return Datatables
     */
    public function setDistinctResponse($array): Datatables
    {
        $this->distinctData = $array;

        return $this;
    }

    /**
     * @return array
     */
    public function getColumns(): array
    {
        return $this->columns->names();
    }

    /**
     * @return Query
     */
    public function getQuery(): Query
    {
        return $this->builder->full;
    }

    /**
     * @param string $column
     * @return Datatables
     */
    public function hide(string $column, $searchable = false): Datatables
    {
        $this->columns->getByName($column)->hide($searchable);

        return $this;
    }

    /**
     * @param mixed $query
     * @return Datatables
     */
    public function query($query, array $escapes = []): Datatables
    {
        $query = $this->db->getQueryString($query);
        $this->builder = new QueryBuilder($query, $this->options, $this->db);
        $this->columns = $this->builder->columns();

        foreach ($escapes as $key => $value) {
            $this->escape($key, $value);
        }

        return $this;
    }

    /**
     * @return Datatables
     */
    public function generate($mode = null)
    {
        $this->debug = ($mode === 'debug');
        $this->debugInfo = $this->debug ? new DebugInfo() : null;

        if ($this->debugInfo) {
            $this->debugInfo->logCall('init', [
                'request' => $_REQUEST, // or $this->options->toArray() if available
            ]);
        }

        // Pass DebugInfo to QueryBuilder (builder) and DB adapter if they support it
        if (is_object($this->builder) && method_exists($this->builder, 'setDebugInfo')) {
            $this->builder->setDebugInfo($this->debugInfo);
        }
        if (is_object($this->db) && method_exists($this->db, 'setDebugInfo')) {
            $this->db->setDebugInfo($this->debugInfo);
        }

        $this->builder->setColumnAttributes();
        $this->builder->setFilteredQuery();
        $this->builder->setFullQuery();
        $this->builder->setEscapes($this->escapes);

        $this->setResponseData();

        if ($this->debugInfo) {
            $this->debugInfo
                ->add('final_sql', $this->lastSqlQuery ?? null)
                ->add('columns', $this->columns->visible()->names() ?? [])
                ->add('warnings', $this->debugWarnings ?? []);
            $this->debugInfo->logCall('generate_complete');
        }

        return $this;
    }

    /**
     * @return array
     */
    protected function getData(): array
    {
        $data = $this->db->query($this->builder->full);

        return array_map([$this, 'prepareRowData'], $data);
    }

    /**
     * @param $row
     * @return array
     */
    protected function prepareRowData($row): array
    {
        $keys = $this->builder->isDataObject() ? $this->columns->names() : array_keys($this->columns->names());

        $values = array_map(function (Column $column) use ($row) {
            return $column->value($row);
        }, $this->columns->visible()->getArrayCopy());

        return array_combine($keys, $values);
    }

    /**
     * @return array
     */
    private function getDistinctData(): array
    {
        foreach ($this->distinctColumn as $column) {
            $output[$column] = array_column($this->db->query($this->builder->getDistinctQuery($column)), $column);
        }

        return $output ?? [];
    }

    public function setTotalRecords(int $total): Datatables
    {
        $this->recordsTotal = $total;

        return $this;
    }

    /**
     *
     */
    public function setResponseData(): void
    {
        $this->queries = [];
        $this->response['draw'] = $this->options->draw();

        if (is_null($this->recordsTotal)) {
            $this->response['recordsTotal'] = $this->db->count($this->builder->query);
            $this->queries['query'] = $this->builder->query;
        } else {
            $this->response['recordsTotal'] = $this->recordsTotal;
        }

        if($this->builder->query->sql === $this->builder->filtered->sql) {
            $this->response['recordsFiltered'] = $this->response['recordsTotal'];
        } else {
            $this->response['recordsFiltered'] = $this->db->count($this->builder->filtered);
            $this->queries['recordsFiltered'] = $this->builder->filtered;
        }

        $this->response['data'] = $this->getData();
        $this->queries['full'] = $this->builder->full;

        if (\count($this->distinctColumn) > 0 || \count($this->distinctData) > 0) {
            $this->response['distinctData'] = array_merge($this->response['distinctData'] ?? [],
                $this->getDistinctData(), $this->distinctData);
        }
    }

    /**
     * @return array
     */
    public function queries(): array
    {
        return $this->queries;
    }

    /**
     * Format the response to always include required DataTables fields.
     * Only include 'error' if an error occurred, and use a generic message.
     *
     * @return array
     */
    private function formatResponse(): array
    {
        $response = [
            'draw' => (int)($this->response['draw'] ?? 0),
            'recordsTotal' => (int)($this->response['recordsTotal'] ?? 0),
            'recordsFiltered' => (int)($this->response['recordsFiltered'] ?? 0),
            'data' => $this->response['data'] ?? [],
        ];
        if (!empty($this->response['error'])) {
            // Only send a generic error message to the client
            $response['error'] = 'An unexpected error occurred. Please try again later.';
        }
        if ($this->debug && $this->debugInfo) {
            $response['debug'] = $this->debugInfo->get();
        }
        return $response;
    }

    /**
     * Format an error response for DataTables, optionally including exception details in debug mode.
     *
     * @param mixed $draw
     * @param \Throwable|null $exception
     * @return array
     */
    private function formatErrorResponse($draw, $exception = null): array
    {
        $response = [
            'draw' => (int)$draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ];

        if ($this->debug && $exception) {
            // Show actual error and trace in debug mode
            $response['error'] = "Exception: " . $exception->getMessage() . "\n" . $exception->getTraceAsString();
        } else {
            // Generic error for production
            $response['error'] = 'An unexpected error occurred. Please try again later.';
        }

        return $response;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->toJson();
        } catch (\Throwable $e) {
            return json_encode($this->formatErrorResponse($this->options->draw(), $e), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        }
    }

    /**
     * @return string
     */
    public function toJson(): string
    {
        try {
            return json_encode($this->formatResponse(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        } catch (\Throwable $e) {
            return json_encode($this->formatErrorResponse($this->options->draw(), $e), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        try {
            return $this->formatResponse();
        } catch (\Throwable $e) {
            return $this->formatErrorResponse($this->options->draw(), $e);
        }
    }
}
