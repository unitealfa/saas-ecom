<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

class SchemaBlueprint extends Blueprint
{
    /**
     * Preserve the documented amount range and cent precision on every connection.
     *
     * @param  string  $column
     * @param  int  $total
     * @param  int  $places
     */
    public function decimal(mixed $column, mixed $total = 14, mixed $places = 2): ColumnDefinition
    {
        return parent::decimal($column, $total, $places);
    }

    /** @param string $column */
    public function uuid(mixed $column = 'uuid'): ColumnDefinition
    {
        $definition = parent::uuid($column)->charset('ascii')->collation('ascii_bin');

        if ($column === 'uuid') {
            $definition->unique();
        }

        return $definition;
    }
}
