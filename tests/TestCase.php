<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
}
