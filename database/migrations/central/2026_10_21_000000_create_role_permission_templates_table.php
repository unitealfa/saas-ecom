<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permission_templates', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->restrictOnDelete();
            $table->unsignedSmallInteger('duration_days')->default(9999);
            $table->primary(['role_id', 'permission_id']);
        });
        DB::statement('ALTER TABLE role_permission_templates ADD CONSTRAINT chk_role_template_duration CHECK (duration_days BETWEEN 1 AND 9999)');
    }

    public function down(): void
    {
        if (DB::table('role_permission_templates')->exists()) {
            throw new LogicException('Restore the approved authorization snapshot before removing permission templates.');
        }
        Schema::dropIfExists('role_permission_templates');
    }
};
