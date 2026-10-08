<?php

namespace App\Enums\Central\Domains;

enum CertificateStatusEnum: int
{
    case PENDING = 1;
    case ACTIVE = 2;
    case ERROR = 3;
    case EXPIRED = 4;
}
