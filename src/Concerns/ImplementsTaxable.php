<?php

declare(strict_types=1);

namespace Headless\Accounting\Concerns;

use Headless\Accounting\Models\TaxClass;
use Illuminate\Database\Eloquent\Model;

/**
 * ImplementsTaxable — drop-in trait for any host-side model that
 * participates in the package's tax engine by exposing a `tax_class_id`
 * (and optional context flags).
 *
 * Schema expected:
 *
 *     $table->unsignedBigInteger('tax_class_id')->nullable();
 *     $table->json('tax_context_overrides')->nullable();
 *
 * Usage:
 *
 *     use Headless\Accounting\Concerns\ImplementsTaxable;
 *
 *     class Service extends Model implements \Headless\Accounting\Contracts\Taxable
 *     {
 *         use ImplementsTaxable;
 *
 *         protected $fillable = ['tax_class_id', 'is_digital'];
 *     }
 *
 * Hosts that use a different attribute name (e.g. `vat_class_id`)
 * can override the protected `$taxClassAttribute` property or simply
 * override `taxClassId()` on their model.
 *
 * @mixin Model
 */
trait ImplementsTaxable
{
    public function taxClassId(): ?int
    {
        $value = $this->attributes[$this->taxClassAttribute()] ?? null;

        return $value !== null ? (int) $value : null;
    }

    /**
     * Default context. Override on the model for richer payloads.
     *
     * @return array<string,mixed>
     */
    public function taxContext(): array
    {
        $column = $this->taxContextAttribute();
        if ($column === null) {
            return [];
        }

        $stored = $this->attributes[$column] ?? null;
        if (is_array($stored)) {
            return $stored;
        }
        if (is_string($stored)) {
            $decoded = json_decode($stored, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /** Resolve the {@see TaxClass} from the foreign key. */
    public function taxClass()
    {
        $relation = $this->taxClassRelation();

        return $relation ? $relation->get() : null;
    }

    /**
     * Subclasses can override this if they need a non-default relation name.
     * Default is the canonical `taxClass()` relation used across the package.
     */
    public function taxClassRelation(): mixed
    {
        if (method_exists($this, 'taxClass')) {
            return $this->taxClass();
        }

        return null;
    }

    protected function taxClassAttribute(): string
    {
        return property_exists($this, 'taxClassAttribute')
            ? $this->taxClassAttribute
            : 'tax_class_id';
    }

    protected function taxContextAttribute(): ?string
    {
        return property_exists($this, 'taxContextAttribute')
            ? $this->taxContextAttribute
            : 'tax_context';
    }
}
