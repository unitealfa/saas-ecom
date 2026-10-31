<?php

namespace App\Http\Controllers\Central\Geography;

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Http\Controllers\Controller;
use App\Services\Central\Geography\GeographyCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GeographyLookupController extends Controller
{
    public function __construct(private readonly GeographyCatalog $catalog) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(GeoZoneTypeEnum::class)],
            'parent_uuid' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        return response()->json($this->catalog->listing($filters));
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json(['data' => $this->catalog->find($uuid)]);
    }
}