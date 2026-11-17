<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class InvalidTicketCodeException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El código debe tener el formato INC-0000');
    }
}
