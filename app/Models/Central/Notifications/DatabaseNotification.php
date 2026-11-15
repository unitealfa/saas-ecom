<?php

namespace App\Models\Central\Notifications;

use Illuminate\Notifications\DatabaseNotification as BaseNotification;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class DatabaseNotification extends BaseNotification
{
    use CentralConnection;
}
