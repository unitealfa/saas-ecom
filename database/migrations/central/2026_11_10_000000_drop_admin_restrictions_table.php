<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException(
                'Cette migration concerne uniquement la base centrale.'
            );
        }

        if (! Schema::hasTable('admin_restrictions')) {
            return;
        }

        // Ne jamais supprimer silencieusement des données existantes.
        if (DB::table('admin_restrictions')->exists()) {
            throw new LogicException(
                'admin_restrictions contient des données. '.
                'Sauvegarder et examiner les enregistrements avant suppression.'
            );
        }

        Schema::dropIfExists('admin_restrictions');
    }

    public function down(): void
    {
        throw new LogicException(
            'Migration irréversible : restaurer la sauvegarde si nécessaire.'
        );
    }
};