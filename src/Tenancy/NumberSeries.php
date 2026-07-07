<?php

declare(strict_types=1);

namespace Headless\Accounting\Tenancy;

use Headless\Accounting\Models\BaseModel;
use Headless\Accounting\Models\Concerns\BelongsToCompany;
use Headless\Accounting\Support\NumberGenerator;

/**
 * NumberSeries — persistent counter used to mint sequential document
 * numbers per company, per prefix, with optional yearly reset.
 *
 *   $invoice = NumberSeries::for(company: $co, prefix: 'INV')->next();
 *   // INV-2026-000132
 *
 * Use {@see NumberGenerator} for ephemeral/development sequential IDs;
 * this class is for production, audit, and threaded safety.
 */
class NumberSeries extends BaseModel
{
    use BelongsToCompany;

    protected string $tableSuffix = 'number_series';

    protected $fillable = [
        'company_id', 'prefix', 'description', 'next_number',
        'pad_length', 'separator', 'include_year', 'reset_yearly',
        'last_reset_year', 'config',
    ];

    protected $casts = [
        'next_number' => 'integer',
        'pad_length' => 'integer',
        'include_year' => 'boolean',
        'reset_yearly' => 'boolean',
        'config' => 'array',
    ];

    public static function for(?int $companyId, string $prefix): self
    {
        return static::query()->firstOrCreate(
            ['company_id' => $companyId, 'prefix' => $prefix],
            ['pad_length' => 6, 'separator' => '-', 'include_year' => true, 'reset_yearly' => true],
        );
    }

    public function next(): string
    {
        $year = (int) date('Y');

        if ($this->reset_yearly && $this->last_reset_year !== $year) {
            $this->next_number = 1;
            $this->last_reset_year = $year;
        }

        $number = (int) $this->next_number;
        $this->next_number++;
        $this->save();

        $parts = [];
        if ($this->include_year) {
            $parts[] = (string) $year;
        }
        $parts[] = str_pad((string) $number, $this->pad_length, '0', STR_PAD_LEFT);

        return $this->prefix.$this->separator.implode($this->separator, $parts);
    }

    public function peek(): int
    {
        return (int) $this->next_number;
    }
}
