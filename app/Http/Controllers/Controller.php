<?php

namespace App\Http\Controllers;

use App\Models\Central\Users\User;

abstract class Controller
{
    protected function centralActor(): User
    {
        $actor = auth('central')->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
