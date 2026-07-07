<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\HasApprovals;

class FakeApprovalSubject extends FakeModel
{
    use HasApprovals;

    protected $table = 'fake_approval_subjects';
}
