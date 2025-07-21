<?php

namespace Ichadhr\Datatables\Iterators;


use FilterIterator;

/**
 * Class GlobalSearchableColumns
 *
 * @package Ichadhr\Datatables\Iterators
 */
class GlobalSearchableColumns extends FilterIterator
{
    /**
     * @return bool
     */
    public function accept(): bool
    {
        return ($this->current()->forceSearch || (!$this->current()->hidden && $this->current()->isSearchable()));
    }
}