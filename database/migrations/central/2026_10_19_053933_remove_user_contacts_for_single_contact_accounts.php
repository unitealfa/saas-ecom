<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = Schema::getConnection();
        if (Schema::hasTable('user_contacts')) {
            $mismatch = $connection->table('user_contacts as c')->join('users as u', 'u.id', '=', 'c.user_id')
                ->where('c.is_primary', true)->whereRaw("(c.kind = 'email' AND NOT (BINARY c.value <=> BINARY u.email)) OR (c.kind = 'phone' AND NOT (c.value <=> u.phone))")->exists();
            if ($mismatch) {
                throw new RuntimeException('Les coordonnées principales doivent être synchronisées avant le retrait du registre.');
            }
            if ($connection->table('user_contacts')->where('is_primary', false)->exists()) {
                throw new RuntimeException('Des coordonnées secondaires existent : les sauvegarder et décider de leur retrait avant migration.');
            }
        }

        $connection->unprepared('DROP TRIGGER IF EXISTS users_contacts_insert');
        $connection->unprepared('DROP TRIGGER IF EXISTS users_contacts_update');
        Schema::dropIfExists('user_contacts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('Le retour aux coordonnées multiples exige une migration explicite et une sauvegarde ; ne pas recréer un registre vide.');
    }
};
