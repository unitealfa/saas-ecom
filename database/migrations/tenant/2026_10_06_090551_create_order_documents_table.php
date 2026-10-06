<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_documents', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('revision_id');
            $table->unsignedBigInteger('media_id');
            $table->unsignedBigInteger('generated_by_id');
            $table->string('number');
            $table->integer('document_version');
            $table->json('issuer_snapshot');
            $table->dateTime('generated_at', 6);
            $table->dateTime('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_documents');
    }
};
