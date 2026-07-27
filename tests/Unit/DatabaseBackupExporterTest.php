<?php

namespace Tests\Unit;

use App\Services\Settings\DatabaseBackupExporter;
use RuntimeException;
use Tests\TestCase;

class DatabaseBackupExporterTest extends TestCase
{
    public function test_rejects_non_pgsql_driver(): void
    {
        config(['database.default' => 'sqlite']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PostgreSQL');

        app(DatabaseBackupExporter::class)->export();
    }

    public function test_builds_pg_dump_command_from_config(): void
    {
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'port' => '5432',
                'database' => 'pusdatin',
                'username' => 'pusdatin',
                'password' => 'secret',
            ],
        ]);

        $command = app(DatabaseBackupExporter::class)->buildCommand();

        $this->assertContains('pg_dump', $command);
        $this->assertContains('--host=127.0.0.1', $command);
        $this->assertContains('--port=5432', $command);
        $this->assertContains('--username=pusdatin', $command);
        $this->assertContains('--dbname=pusdatin', $command);
        $this->assertContains('--no-owner', $command);
        $this->assertContains('--no-acl', $command);
    }
}
