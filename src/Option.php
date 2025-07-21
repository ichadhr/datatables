<?php


namespace Ichadhr\Datatables;

use Ichadhr\Datatables\Http\Request;

/**
 * Class Option
 * @package Ichadhr\Datatables
 */
class Option
{
    /**
     * @var Request
     */
    private $request;

    /**
     * Option constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return int
     */
    public function draw(): int
    {
        return (int) ($this->request->get('draw') ?? 0);
    }

    /**
     * @return int
     */
    public function start(): int
    {
        return (int) ($this->request->get('start') ?? 0);
    }

    /**
     * @return int
     */
    public function length(): int
    {
        return (int) ($this->request->get('length') ?? 0);
    }

    /**
     * @return string
     */
    public function searchValue(): string
    {
        $search = $this->request->get('search');
        return (is_array($search) && isset($search['value'])) ? (string) $search['value'] : '';
    }

    /**
     * TODO: Regex search is not currently supported. This method is reserved for future implementation.
     * DataTables may send a 'regex' flag, but the backend will ignore it and perform a normal LIKE search.
     *
     * @return bool
     */
    public function searchRegex(): bool
    {
        // TODO: Implement regex search support in the query builder and adapters if needed.
        $search = $this->request->get('search');
        return (is_array($search) && isset($search['regex'])) ? (bool) $search['regex'] : false;
    }

    /**
     * @return array
     */
    public function order(): array
    {
        $order = $this->request->get('order');
        if (!is_array($order)) {
            return [];
        }
        $validated = [];
        foreach ($order as $item) {
            if (
                is_array($item) &&
                isset($item['column'], $item['dir']) &&
                (is_int($item['column']) || ctype_digit($item['column'])) &&
                in_array($item['dir'], ['asc', 'desc'], true)
            ) {
                $validated[] = [
                    'column' => (int)$item['column'],
                    'dir' => $item['dir']
                ];
            }
        }
        return $validated;
    }

    /**
     * @return array
     */
    public function columns(): array
    {
        $columns = $this->request->get('columns');
        if (!is_array($columns)) {
            return [];
        }
        $validated = [];
        foreach ($columns as $col) {
            if (
                is_array($col) &&
                isset($col['data'], $col['searchable'], $col['orderable'], $col['search']) &&
                is_array($col['search']) &&
                isset($col['search']['value'])
            ) {
                $validated[] = [
                    'data' => $col['data'],
                    'name' => isset($col['name']) ? (string)$col['name'] : '',
                    'searchable' => filter_var($col['searchable'], FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]) ?? false,
                    'orderable' => filter_var($col['orderable'], FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]) ?? false,
                    'search' => [
                        'value' => (string)$col['search']['value'],
                        'regex' => isset($col['search']['regex']) ? (bool)$col['search']['regex'] : false
                    ]
                ];
            }
        }
        return $validated;
    }
}
