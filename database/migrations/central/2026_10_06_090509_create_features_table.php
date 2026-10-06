<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->unsignedTinyInteger('value_type')->comment('FeatureValueTypeEnum: 1, 2');
            $table->string('unit')->nullable();
            $table->unsignedTinyInteger('quota_scope')->comment('QuotaScopeEnum: 1, 2');
            $table->unsignedTinyInteger('period')->comment('QuotaPeriodEnum: 1, 2, 3, 4');
            $table->boolean('is_active');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
