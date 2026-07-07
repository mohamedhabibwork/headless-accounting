<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Contracts\Auditable;

class FakeAuditable extends FakeModel implements Auditable
{
    public function auditIdentifier(): string
    {
        return sprintf('FAKE-%d', (int) $this->id);
    }

    public function auditActor(): mixed
    {
        return null;
    }

    /** @return array<string,mixed> */
    public function auditContext(): array
    {
        return ['source' => 'phpunit'];
    }
}
