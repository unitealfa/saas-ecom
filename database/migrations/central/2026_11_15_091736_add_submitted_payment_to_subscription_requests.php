<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_requests', function (Blueprint $table): void {
            $table->text('submitted_payment')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('subscription_requests')->whereNotNull('submitted_payment')->exists()) {
            throw new LogicException('Submitted bank evidence must be preserved.');
        }
        Schema::table('subscription_requests', function (Blueprint $table): void {
            $table->dropColumn('submitted_payment');
        });
    }
};
