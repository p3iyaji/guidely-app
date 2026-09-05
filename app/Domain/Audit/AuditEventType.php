<?php

namespace App\Domain\Audit;

enum AuditEventType: string
{
    case LoginSuccess = 'auth.login.success';
    case LoginFailed = 'auth.login.failed';
    case Logout = 'auth.logout';
}
