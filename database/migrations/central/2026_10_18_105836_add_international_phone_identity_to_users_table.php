<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $report = app(\App\Services\Central\Users\ContactMigrationAudit::class)->inspect();
        if ($report['invalid'] !== [] || $report['duplicates'] !== []) {
            throw new RuntimeException('Coordonnées à corriger avant migration (aucune fusion automatique) : '.json_encode($report, JSON_THROW_ON_ERROR));
        }
        if (! Schema::hasColumn('users', 'phone_country_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('phone_country_id')->nullable()->constrained('countries')->restrictOnDelete()->restrictOnUpdate();
            });
        }
        $connection = Schema::getConnection();
        $phones = app(\App\Services\Central\Users\PhoneNumberNormalizer::class);
        $connection->transaction(function () use ($connection, $phones): void {
            $connection->table('users')->orderBy('id')->chunkById(200, function ($users) use ($connection, $phones): void {
                foreach ($users as $user) {
                    $countryId = $user->phone_country_id ?? $user->country_id;
                    $code = $connection->table('countries')->where('id', $countryId)->value('code');
                    $phone = null;
                    if ($user->phone !== null && trim($user->phone) !== '') {
                        if ($user->phone_country_id !== null) {
                            $phone = $phones->normalize($user->phone, $code);
                        } else {
                            $historical = $phones->historical($user->phone, $code);
                            $phone = $historical['value'];
                            $countryId = $connection->table('countries')->where('code', $historical['region'])->value('id');
                        }
                        if ($countryId === null) {
                            throw new RuntimeException('Le pays du téléphone historique doit être enregistré avant migration.');
                        }
                    }
                    $connection->table('users')->where('id', $user->id)->update([
                        'phone' => $phone, 'phone_country_id' => $phone === null ? null : $countryId,
                    ]);
                }
            });
        });
        if (! Schema::hasIndex('users', 'users_phone_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique('phone');
            });
        }
        $constraint = $connection->table('information_schema.TABLE_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', $connection->getDatabaseName())
            ->where('TABLE_NAME', 'users')->where('CONSTRAINT_NAME', 'chk_users_phone_country')->exists();
        if (! $constraint) {
            $connection->statement("ALTER TABLE users ADD CONSTRAINT chk_users_phone_country CHECK ((phone IS NULL AND phone_country_id IS NULL) OR (phone IS NOT NULL AND phone_country_id IS NOT NULL AND phone REGEXP '^[+][1-9][0-9]{1,14}$'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('Le pays propre au téléphone ne peut pas être retiré sans perdre son interprétation. Préparer une migration corrective explicite.');
    }
};
