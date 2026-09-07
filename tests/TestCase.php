<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // Worktree uses a shared vendor symlink; inferBasePath() would resolve to the
        // main checkout. Always boot this worktree's application root instead.
        $app = require dirname(__DIR__).'/bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * @return list<list<mixed>>
     */
    protected function xlsxRows(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-export-').'.xlsx';
        file_put_contents($path, $content);

        try {
            $spreadsheet = IOFactory::load($path);

            return $spreadsheet->getActiveSheet()->toArray();
        } finally {
            @unlink($path);
        }
    }
}
