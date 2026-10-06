<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_terms_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('revision_id');
            $table->string('operation_key')->unique();
            $table->string('sales_terms_version');
            $table->char('terms_hash', 64)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->dateTime('accepted_at', 6);
            $table->unsignedTinyInteger('acceptance_mode')->comment('TermsAcceptanceModeEnum: 1, 2');
            $table->json('sanitized_proof')->nullable();
            $table->dateTime('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_terms_acceptances');
    }
};
