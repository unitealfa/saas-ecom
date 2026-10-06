<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('media_id')->nullable();
            $table->unsignedTinyInteger('record_type')->comment('CategoryRecordTypeEnum: 1, 2');
            $table->unsignedTinyInteger('parent_record_type')->nullable()->storedAs('CASE WHEN parent_id IS NOT NULL THEN 1 ELSE NULL END');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->integer('position');
            $table->boolean('is_active');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
