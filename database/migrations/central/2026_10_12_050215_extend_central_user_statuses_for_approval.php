<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('tenancy.database.central_connection');
    }

    public function up(): void
    {
        $this->ensureCentralMysql();
        DB::connection($this->getConnection())->statement(
            'ALTER TABLE `users` DROP CHECK `ck_users_24b1d83b73`, ADD CONSTRAINT `ck_users_24b1d83b73` CHECK (status IN (1, 2, 3, 4, 5, 6))'
        );
    }

    public function down(): void
    {
        $this->ensureCentralMysql();
        $connection = DB::connection($this->getConnection());
        if ($connection->table('users')->whereIn('status', [5, 6])->exists()) {
            throw new RuntimeException('Impossible de retirer les statuts tant que des comptes sont en attente ou refusés. Aucun compte ne sera converti automatiquement.');
        }
        $connection->statement(
            'ALTER TABLE `users` DROP CHECK `ck_users_24b1d83b73`, ADD CONSTRAINT `ck_users_24b1d83b73` CHECK (status IN (1, 2, 3, 4))'
        );
    }

    private function ensureCentralMysql(): void
    {
        if (tenancy()->initialized || DB::connection($this->getConnection())->getDriverName() !== 'mysql') {
            throw new LogicException('Cette évolution des comptes exige le contexte central MySQL.');
        }
    }
};
