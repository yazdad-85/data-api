<?php

namespace App\Services\Settings;

use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupExporter
{
    public function assertPostgres(): void
    {
        if (config('database.default') !== 'pgsql') {
            throw new RuntimeException('Backup database hanya tersedia untuk PostgreSQL.');
        }
    }

    /**
     * @param callable(string): void $writeChunk
     */
    public function streamTo(callable $writeChunk): void
    {
        $this->assertPostgres();

        $process = new Process($this->buildCommand(), null, [
            'PGPASSWORD' => (string) config('database.connections.pgsql.password', ''),
        ]);
        $process->setTimeout(120);
        $process->disableOutput();
        $process->run(function (string $type, string $chunk) use ($writeChunk): void {
            if ($type === Process::OUT) {
                $writeChunk($chunk);
            }
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Backup gagal dijalankan. Hubungi operator server.');
        }
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
