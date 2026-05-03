<?php

namespace App\Enum;

enum AuditLogAction: string
{
    case INSERT = 'INSERT';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE';
}
