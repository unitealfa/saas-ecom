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
            throw new RuntimeException('Corriger les conflits de coordonnées avant de créer leur registre.');
        }
        $connection = Schema::getConnection();
        if ($connection->table('users')->selectRaw('LOWER(TRIM(email)) AS normalized')->groupBy('normalized')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Des adresses e-mail normalisées appartiennent à plusieurs comptes.');
        }
        Schema::create('user_contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('country_id')->nullable()->constrained('countries')->restrictOnDelete()->restrictOnUpdate();
            $table->string('kind');
            $table->string('value');
            $table->dateTime('verified_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedTinyInteger('primary_slot')->nullable()->storedAs('CASE WHEN is_primary = TRUE THEN 1 ELSE NULL END');
            $table->timestamps();
            $table->unique(['kind', 'value'], 'user_contacts_kind_value_unique');
            $table->unique(['user_id', 'kind', 'primary_slot'], 'user_contacts_primary_unique');
        });
        $connection->statement("ALTER TABLE user_contacts ADD CONSTRAINT chk_user_contacts_kind CHECK ((kind = 'email' AND country_id IS NULL AND BINARY value = BINARY LOWER(TRIM(value))) OR (kind = 'phone' AND country_id IS NOT NULL AND value REGEXP '^[+][1-9][0-9]{1,14}$'))");
        $connection->transaction(function () use ($connection): void {
            $connection->table('users')->orderBy('id')->chunkById(200, function ($users) use ($connection): void {
                foreach ($users as $user) {
                    $connection->table('users')->where('id', $user->id)->update(['email' => strtolower(trim($user->email))]);
                    foreach (['email', 'phone'] as $kind) {
                        if ($user->$kind === null || $user->$kind === '') {
                            continue;
                        }
                        $connection->table('user_contacts')->insert([
                            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $user->id,
                            'kind' => $kind, 'value' => $kind === 'email' ? strtolower(trim($user->$kind)) : $user->$kind,
                            'country_id' => $kind === 'phone' ? $user->phone_country_id : null,
                            'verified_at' => $user->{$kind.'_verified_at'}, 'is_primary' => true,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            });
        });
        foreach (['INSERT', 'UPDATE'] as $event) {
            $body = '';
            foreach (['email', 'phone'] as $kind) {
                $country = $kind === 'phone' ? 'NEW.phone_country_id' : 'NULL';
                if ($kind === 'phone') {
                    $body .= "IF NEW.phone IS NULL AND EXISTS (SELECT 1 FROM user_contacts WHERE user_id = NEW.id AND kind = 'phone') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A primary phone is required for registered phone contacts'; END IF; ";
                }
                if ($event === 'UPDATE') {
                    $body .= "IF NOT (NEW.$kind <=> OLD.$kind) OR NOT (NEW.{$kind}_verified_at <=> OLD.{$kind}_verified_at)"
                        .($kind === 'phone' ? ' OR NOT (NEW.phone_country_id <=> OLD.phone_country_id)' : '').' THEN ';
                }
                $body .= "
                    IF EXISTS (SELECT 1 FROM user_contacts WHERE kind = '$kind' AND value = NEW.$kind AND user_id <> NEW.id) THEN
                        SIGNAL SQLSTATE '23000' SET MYSQL_ERRNO = 1062, MESSAGE_TEXT = 'user_contacts_kind_value_unique:$kind';
                    END IF;
                    UPDATE user_contacts SET is_primary = FALSE, updated_at = CURRENT_TIMESTAMP WHERE user_id = NEW.id AND kind = '$kind' AND is_primary = TRUE;
                    IF NEW.$kind IS NOT NULL THEN
                        IF EXISTS (SELECT 1 FROM user_contacts WHERE kind = '$kind' AND value = NEW.$kind AND user_id = NEW.id) THEN
                            UPDATE user_contacts SET country_id = $country, verified_at = NEW.{$kind}_verified_at, is_primary = TRUE, updated_at = CURRENT_TIMESTAMP
                                WHERE kind = '$kind' AND value = NEW.$kind AND user_id = NEW.id;
                        ELSE
                            INSERT INTO user_contacts (uuid, user_id, kind, value, country_id, verified_at, is_primary, created_at, updated_at)
                            VALUES (LOWER(CONCAT(HEX(RANDOM_BYTES(4)), '-', HEX(RANDOM_BYTES(2)), '-4', RIGHT(HEX(RANDOM_BYTES(2)), 3), '-', HEX(8 | (ORD(RANDOM_BYTES(1)) & 3)), RIGHT(HEX(RANDOM_BYTES(2)), 3), '-', HEX(RANDOM_BYTES(6)))), NEW.id, '$kind', NEW.$kind, $country, NEW.{$kind}_verified_at, TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
                        END IF;
                    END IF;";
                if ($event === 'UPDATE') {
                    $body .= ' END IF; ';
                }
            }
            $connection->unprepared('CREATE TRIGGER users_contacts_'.strtolower($event).' AFTER '.$event.' ON users FOR EACH ROW BEGIN '.$body.' END');
        }
        $connection->statement('ALTER TABLE users ADD CONSTRAINT chk_users_email_normalized CHECK (BINARY email = BINARY LOWER(TRIM(email)))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('Le registre peut contenir des coordonnées secondaires : préparer une migration corrective sans perte.');
    }
};
