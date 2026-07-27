<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $this->replaceResultConstraint(['success', 'failure', 'failed', 'blocked']);

            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->enum('result', ['success', 'failure', 'failed', 'blocked'])->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $this->replaceResultConstraint(['success', 'failed', 'blocked']);

            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->enum('result', ['success', 'failed', 'blocked'])->change();
        });
    }

    /**
     * @param list<string> $values
     */
    private function replaceResultConstraint(array $values): void
    {
        $allowedValues = implode(', ', array_map(
            static fn (string $value): string => "'{$value}'",
            $values,
        ));

        DB::unprepared(<<<SQL
            DO $$
            DECLARE
                constraint_name text;
            BEGIN
                SELECT conname
                INTO constraint_name
                FROM pg_constraint
                WHERE conrelid = 'audit_logs'::regclass
                  AND contype = 'c'
                  AND pg_get_constraintdef(oid) LIKE '%result%';

                IF constraint_name IS NOT NULL THEN
                    EXECUTE format('ALTER TABLE audit_logs DROP CONSTRAINT %I', constraint_name);
                END IF;

                ALTER TABLE audit_logs
                    ADD CONSTRAINT audit_logs_result_check
                    CHECK (result IN ({$allowedValues}));
            END
            $$;
        SQL);
    }
};
