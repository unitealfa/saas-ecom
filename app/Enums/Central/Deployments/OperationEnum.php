<?php

namespace App\Enums\Central\Deployments;

enum OperationEnum: int
{
    case PROVISIONING = 1;
    case MIGRATION = 2;
}
