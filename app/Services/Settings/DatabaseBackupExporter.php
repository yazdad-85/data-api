<?php

namespace App\Services\Settings;

use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupExporter
{
    /**
     * @return array{0: string, 1: string} [contents, suggestedFilename]
     */
    public function export(): array
    {
        if (config('database.default') !== 'pgsql') {
            throw new RuntimeException('Backup database hanya tersedia untuk PostgreSQL.');
        }

        $process = new Process($this->buildCommand(), null, [
            'PGPASSWORD' => (string) config('database.connections.pgsql.password', ''),
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Backup gagal dijalankan. Hubungi operator server.');
        }

        return [
            $process->getOutput(),
            'pusat-data-'.now()->format('Ymd-His').'.sql',
        ];
    }

    /**
     * @return list<string>
     */
    public function buildCommand(): array
    {
        $config = config('database.connections.pgsql');

        return [
            'pg_dump',
            '--host='.(string) ($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? '5432'),
            '--username='.(string) ($config['username'] ?? ''),
            '--dbname='.(string) ($config['database'] ?? ''),
            '--no-owner',
            '--no-acl',
            '--clean',
            '--if-exists',
        ];
    }
}
