<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class TicketNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La incidencia solicitada no existe');
    }
}
