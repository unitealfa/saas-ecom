<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Tenant\Shop\ShopProfileService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopBrandingController extends Controller
{
    public function __invoke(string $kind, ShopProfileService $profiles): StreamedResponse
    {
        return $profiles->branding($kind);
    }
}
