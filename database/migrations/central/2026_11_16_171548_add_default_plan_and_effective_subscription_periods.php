<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedTinyInteger('is_default')->nullable()->unique();
        });
        DB::statement('ALTER TABLE plans ADD CONSTRAINT ck_plans_default CHECK (is_default IS NULL OR is_default = 1)');
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dateTime('effective_starts_at')->nullable();
            $table->dateTime('effective_ends_at')->nullable();
        });
        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT ck_subscription_effective_period CHECK ((effective_starts_at IS NULL AND effective_ends_at IS NULL) OR (effective_starts_at IS NOT NULL AND effective_ends_at IS NOT NULL AND effective_ends_at > effective_starts_at))');
    }

    public function down(): void
    {
        if (DB::table('subscriptions')->whereNotNull('effective_starts_at')->exists()
            || DB::table('plans')->whereNotNull('is_default')->exists()) {
            throw new LogicException('La configuration et les périodes effectives doivent être conservées.');
        }
        DB::statement('ALTER TABLE subscriptions DROP CHECK ck_subscription_effective_period');
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn(['effective_starts_at', 'effective_ends_at']));
        DB::statement('ALTER TABLE plans DROP CHECK ck_plans_default');
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropUnique(['is_default']);
            $table->dropColumn('is_default');
        });
    }
};
