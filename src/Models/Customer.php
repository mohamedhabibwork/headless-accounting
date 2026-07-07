<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Models\Concerns\RecordsEvents;
use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Customer extends BaseModel
{
    use HasFactory, RecordsEvents;

    protected string $tableSuffix = 'customers';

    protected $fillable = [
        'owner_type', 'owner_id', 'email',
        'first_name', 'last_name', 'company', 'vat_id', 'phone',
        'default_currency', 'default_locale',
        'email_verified_at', 'metadata',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'owner');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            CustomerGroup::class,
            Config::string('headless-accounting.table_prefix', 'ha_').'customer_group_members'
        );
    }

    public function fullName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }
}
