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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('transition_mode')->default(2);
            $table->unsignedBigInteger('replaces_subscription_id')->nullable();
            $table->foreign(['replaces_subscription_id', 'user_id'], 'fk_subscriptions_replacement_owner')
                ->references(['id', 'user_id'])->on('subscriptions')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT ck_subscriptions_transition_mode CHECK (transition_mode IN (1, 2))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('subscriptions')->whereNotNull('replaces_subscription_id')->orWhere('transition_mode', 1)->exists()) {
            throw new LogicException('Subscription replacement history must be preserved.');
        }
        DB::statement('ALTER TABLE subscriptions DROP CHECK ck_subscriptions_transition_mode');
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign('fk_subscriptions_replacement_owner');
            $table->dropColumn(['transition_mode', 'replaces_subscription_id']);
        });
    }
};
