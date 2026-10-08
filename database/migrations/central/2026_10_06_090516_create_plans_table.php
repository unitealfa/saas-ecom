<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->string('code');
            $table->integer('version');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('monthly_price');
            $table->decimal('annual_price');
            $table->boolean('is_active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['code', 'version'], 'uq_plans_f5b433d863');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
