<?php

namespace App\Domain\Wallet\Exceptions;

use Exception;

class DuplicateTransactionException extends Exception
{
    public function __construct()
    {
        parent::__construct('Duplicate transaction reference.');
    }
}