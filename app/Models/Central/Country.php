<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use Database\Factories\Central\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** @property int $id */
#[Fillable(['code', 'name_fr', 'name_en', 'name_ar', 'is_active'])]
#[Hidden(['id'])]
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use CentralConnection, HasFactory, HasPublicUuid;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
