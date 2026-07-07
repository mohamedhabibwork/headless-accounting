<?php

declare(strict_types=1);

namespace Headless\Accounting\Accounting;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;

class JournalTemplate extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'journal_templates';

    protected $fillable = ['company_id', 'code', 'name', 'description', 'currency', 'lines', 'tags', 'active'];

    protected $casts = ['lines' => 'array', 'tags' => 'array', 'active' => 'boolean'];

    /**
     * Materializes the template into line rows that the Journal::post()
     * method understands.
     */
    public function materializeRows(array $overrides = []): array
    {
        $rows = (array) $this->lines;
        if ($overrides !== []) {
            foreach ($overrides as $k => $v) {
                $rows[$k] = is_array($v) ? array_merge((array) ($rows[$k] ?? []), $v) : $v;
            }
        }

        return $rows;
    }
}
