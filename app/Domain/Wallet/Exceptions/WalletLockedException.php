<?php

namespace App\Domain\Wallet\Exceptions;

use Exception;

class WalletLockedException extends Exception
{
    public function __construct()
    {
        parent::__construct('Wallet is currently locked.');
    }
}