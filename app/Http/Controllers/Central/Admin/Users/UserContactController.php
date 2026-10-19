<?php

namespace App\Http\Controllers\Central\Admin\Users;

use App\Models\Central\User;
use Illuminate\Http\Request;

class UserContactController extends \App\Http\Controllers\Central\Settings\UserContactController
{
    protected function target(Request $request): User
    {
        $target = $request->route('user');
        abort_unless($target instanceof User, 404);

        return $target;
    }
}
