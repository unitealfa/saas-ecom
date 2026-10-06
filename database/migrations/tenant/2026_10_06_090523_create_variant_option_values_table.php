<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_option_values', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('option_id');
            $table->unsignedBigInteger('value_id');
            $table->unsignedTinyInteger('option_record_type')->nullable(false)->storedAs('1')->comment('ProductOptionRecordTypeEnum: 1, 2');
            $table->unsignedTinyInteger('value_record_type')->nullable(false)->storedAs('2')->comment('ProductOptionRecordTypeEnum: 1, 2');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_option_values');
    }
};
