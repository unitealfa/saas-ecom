<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

class SchemaBlueprint extends Blueprint
{
    private const int AMOUNT_PRECISION = 14;

    private const int AMOUNT_SCALE = 2;

    private const string PUBLIC_UUID_COLUMN = 'uuid';

    private const string UUID_CHARSET = 'ascii';

    private const string UUID_COLLATION = 'ascii_bin';

    public function decimal(mixed $column, mixed $total = self::AMOUNT_PRECISION, mixed $places = self::AMOUNT_SCALE): ColumnDefinition
    {
        return parent::decimal($column, $total, $places);
    }

    public function uuid(mixed $column = self::PUBLIC_UUID_COLUMN): ColumnDefinition
    {
        $definition = parent::uuid($column);

        if ($this->usesMysqlCollations()) {
            $definition->charset(self::UUID_CHARSET)->collation(self::UUID_COLLATION);
        }

        if ($column === self::PUBLIC_UUID_COLUMN) {
            $definition->unique();
        }

        return $definition;
    }

    private function usesMysqlCollations(): bool
    {
        return in_array($this->connection->getDriverName(), ['mysql', 'mariadb'], true);
    }
}