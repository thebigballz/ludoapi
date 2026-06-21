<?php

namespace App\Domain\Game\Exceptions;

use Exception;

class InvalidGameStateException extends Exception
{
    public function __construct(string $message = 'Invalid game state.')
    {
        parent::__construct($message);
    }
}