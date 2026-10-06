<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedTinyInteger('record_type')->comment('ProductOptionRecordTypeEnum: 1, 2');
            $table->unsignedTinyInteger('parent_record_type')->nullable()->storedAs('CASE WHEN parent_id IS NOT NULL THEN 1 ELSE NULL END')->comment('ProductOptionRecordTypeEnum: 1, 2');
            $table->string('name')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'NOCASE' : 'utf8mb4_0900_ai_ci');
            $table->string('identity_code')->nullable();
            $table->unsignedTinyInteger('display_type')->nullable()->comment('OptionDisplayTypeEnum: 1, 2, 3');
            $table->char('color_hex', 7)->nullable();
            $table->integer('position');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};
