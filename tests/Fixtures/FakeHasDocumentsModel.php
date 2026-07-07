<?php

declare(strict_types=1);

namespace Headless\Accounting\Tests\Fixtures;

use Headless\Accounting\Concerns\HasDocuments;

class FakeHasDocumentsModel extends FakeModel
{
    use HasDocuments;

    protected $table = 'fake_documents_models';
}
