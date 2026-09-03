<?php

namespace App\Console\Commands;

use App\Services\LegacyImporter;
use Illuminate\Console\Command;

class ImportLegacy extends Command
{
    protected $signature = 'app:import-legacy {db? : Path to the legacy fge_server.db sqlite file}';

    protected $description = 'Import legacy fge_server.db data (users, site content, gallery, messages) into the current schema';

    public function handle(): int
    {
        $dbPath = $this->argument('db') ?: base_path('../fge-backend/fge_server.db');

        if (! is_file($dbPath)) {
            $this->error("Database file not found: $dbPath");
            return self::FAILURE;
        }

        $stats = (new LegacyImporter($dbPath))->import();

        foreach ($stats as $label => $count) {
            $this->info("$label: $count");
        }
        $this->info('Import complete.');

        return self::SUCCESS;
    }
}