<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_tags', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('tag_id');
            $table->unsignedTinyInteger('tag_record_type')->nullable(false)->storedAs('2');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tags');
    }
};
