<?php

namespace Ichadhr\Datatables;

/**
 * Class Column
 *
 * @package Ichadhr\Datatables
 */
class Column
{
    /**
     * Column name
     *
     * @var
     */
    public $name;

    /**
     * Column visibility
     *
     * @var bool
     */
    public $hidden = false;

    /**
     *
     * @var bool
     */
    public $forceSearch = false;

    /**
     * Callback function
     *
     * @var \Closure
     */
    public $closure;

    /**
     * @var array
     */
    public $attr = [];

    /**
     * @var bool
     */
    public $interaction = true;

    /**
     * Custom filter
     * @var \Closure
     */
    public $customIndividualFilter;

    /**
     * Custom filter
     * @var \Closure
     */
    public $customGlobalFilter;

    /**
     *
     * @var string
     */
    public $customFilterType;

    /**
     * Column constructor.
     *
     * @param $name
     */
    public function __construct($name)
    {
        $this->name = $name;
        $this->attr['searchable'] = false;
        $this->attr['orderable'] = false;
        $this->attr['search'] = ['value' => ''];
    }

    /**
     * @param $row array
     * @return string
     */
    public function value($row): string
    {
        if ($this->closure instanceof \Closure) {
            return call_user_func($this->closure, $row) ?? '';
        }

        return $row[$this->name] ?? '';
    }

    /**
     * Set visibility of the column.
     * @param bool $searchable
     */
    public function hide(bool $searchable = false): void
    {
        $this->hidden = true;
        $this->forceSearch = $searchable;
    }

    /**
     * @return bool
     */
    public function hasCustomIndividualFilter(): bool
    {
        return $this->customIndividualFilter instanceof \Closure;
    }

    /**
     * @return bool
     */
    public function hasCustomGlobalFilter(): bool
    {
        return $this->customGlobalFilter instanceof \Closure;
    }

    /**
     * @return bool
     */
    public function isSearchable(): bool
    {
        return ($this->interaction && filter_var($this->attr['searchable'], FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * @return bool
     */
    public function isOrderable(): bool
    {
        return ($this->interaction && filter_var($this->attr['orderable'], FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * @param string $property data as object, fallback data as string  
     * @return string
     */
    public function data($property = '_'): string
    {
        return $this->attr['data'][$property] ?? $this->attr['data']['_'] ?? $this->attr['data'] ?? '';
    }

    /**
     * @return string
     */
    public function searchValue(): string
    {
        return $this->attr['search']['value'] ?? '';
    }
}
