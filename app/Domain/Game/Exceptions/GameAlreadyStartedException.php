<?php

namespace App\Domain\Game\Exceptions;

use Exception;

class GameAlreadyStartedException extends Exception
{
    public function __construct()
    {
        parent::__construct('This game has already started.');
    }
}