<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_history', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('previous_revision_id')->nullable();
            $table->unsignedBigInteger('next_revision_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->unsignedTinyInteger('contact_outcome')->nullable()->comment('ContactOutcomeEnum: 1, 2, 3, 4, 5');
            $table->dateTime('next_callback_at', 6)->nullable();
            $table->unsignedTinyInteger('previous_status')->nullable()->comment('OrderStatusEnum: 1, 2, 5');
            $table->unsignedTinyInteger('new_status')->nullable()->comment('OrderStatusEnum: 1, 2, 5');
            $table->json('changes')->nullable();
            $table->text('note')->nullable();
            $table->char('correlation_id', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->unsignedTinyInteger('origin')->comment('ActivityOriginEnum: 1, 2, 3, 4');
            $table->dateTime('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_history');
    }
};
