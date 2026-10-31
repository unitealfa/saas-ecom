<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geographic_areas', function (Blueprint $table): void {
            $table->date('effective_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->table('geographic_areas')->whereNull('effective_at')->exists()) {
            throw new LogicException('Unknown effective dates must be resolved before reverting their nullability.');
        }
        Schema::table('geographic_areas', function (Blueprint $table): void {
            $table->date('effective_at')->nullable(false)->change();
        });
    }
};
