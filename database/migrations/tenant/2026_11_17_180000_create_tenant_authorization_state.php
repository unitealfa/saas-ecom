<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->create('tenant_authorization_state', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('individual_only')->default(false);
            $table->dateTime('converted_at')->nullable();
            $table->timestamps();
        });
        DB::connection('tenant')->table('tenant_authorization_state')->insert([
            'id' => 1, 'individual_only' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('tenant_authorization_state');
    }
};
