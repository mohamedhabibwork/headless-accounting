<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Catch-all fixture base class. We keep a class per scenario so each
 * one stays small and remains PSR-4 compliant.
 */
class FakeModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}
