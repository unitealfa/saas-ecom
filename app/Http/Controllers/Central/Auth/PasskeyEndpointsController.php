<?php

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PasskeyEndpointsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'enroll' => route('security.edit'),
            'manage' => route('security.edit'),
        ]);
    }
}
