<?php

namespace App\Domain\Wallet\Exceptions;

use Exception;

class InsufficientBalanceException extends Exception
{
    public function __construct()
    {
        parent::__construct('Insufficient wallet balance.');
    }
}