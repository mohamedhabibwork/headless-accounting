<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Traits;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * RefreshSchema — driver-agnostic test hook.
 *
 * Persistent engines (Postgres / MySQL) are handled by
 * {@see RefreshDatabase}, which runs
 * `migrate:fresh` once per test process and truncates tables between
 * tests to keep data isolated. SQLite enforces foreign keys at the
 * connection level via `foreign_key_constraints` in config/database.php,
 * so no driver-specific setup is required here.
 *
 *   use RefreshSchema;
 *
 *   protected function setUp(): void { parent::setUp(); $this->refreshSchema(); }
 */
trait RefreshSchema
{
    public function refreshSchema(): void
    {
        //
    }
}
