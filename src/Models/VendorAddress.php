<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorAddress extends BaseModel
{
    protected string $tableSuffix = 'vendor_addresses';

    protected $fillable = ['vendor_id', 'type', 'address_lines', 'city', 'region', 'country_code', 'postal_code'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
