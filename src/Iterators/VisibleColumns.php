<?php

namespace Ichadhr\Datatables\Iterators;


use FilterIterator;

/**
 * Class VisibleColumns
 *
 * @package Ichadhr\Datatables\Iterators
 */
class VisibleColumns extends FilterIterator
{

    /**
     * @return bool
     */
    public function accept(): bool
    {
        return !$this->current()->hidden;
    }
}