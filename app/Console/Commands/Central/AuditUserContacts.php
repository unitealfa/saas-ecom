<?php

namespace App\Console\Commands\Central;

use App\Services\Central\Users\ContactMigrationAudit;
use Illuminate\Console\Command;

class AuditUserContacts extends Command
{
    protected $signature = 'aydra:audit-user-contacts';

    protected $description = 'Contrôler les téléphones centraux en lecture seule avant migration.';

    public function handle(ContactMigrationAudit $audit): int
    {
        $report = $audit->inspect();
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $report['invalid'] === [] && $report['duplicates'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
