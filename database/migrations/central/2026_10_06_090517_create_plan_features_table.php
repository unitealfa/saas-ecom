<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('feature_id');
            $table->boolean('is_active');
            $table->bigInteger('limit')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
