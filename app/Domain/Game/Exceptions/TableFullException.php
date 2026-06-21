<?php

namespace App\Domain\Game\Exceptions;

use Exception;

class TableFullException extends Exception
{
    public function __construct()
    {
        parent::__construct('This table is full.');
    }
}