<?php

namespace Ichadhr\Datatables\Iterators;


use FilterIterator;

/**
 * Class IndividualSearchableColumns
 *
 * @package Ichadhr\Datatables\Iterators
 */
class IndividualSearchableColumns extends FilterIterator
{
    /**
     * @return bool
     */
    public function accept(): bool
    {
        return $this->current()->searchValue() !== '' || $this->current()->hasCustomIndividualFilter();
    }
}
