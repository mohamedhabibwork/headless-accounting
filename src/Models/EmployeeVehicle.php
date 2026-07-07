<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\HR\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeVehicle extends Model
{
    protected $table = 'emp_vehicles';

    protected $fillable = ['employee_id', 'plate', 'description', 'mileage_rate_minor_per_km'];

    protected $casts = ['mileage_rate_minor_per_km' => 'float'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
