<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared("CREATE TRIGGER users_immutable_identity BEFORE UPDATE ON users FOR EACH ROW BEGIN IF NOT (OLD.uuid <=> NEW.uuid) OR NOT (OLD.central_user_uuid <=> NEW.central_user_uuid) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable local identity'; END IF; END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS users_immutable_identity');
    }
};
