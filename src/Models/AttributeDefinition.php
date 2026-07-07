<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

class AttributeDefinition extends BaseModel
{
    protected string $tableSuffix = 'attribute_definitions';

    protected $fillable = ['code', 'name', 'type', 'translatable', 'config'];

    protected $casts = [
        'translatable' => 'boolean',
        'config' => 'array',
    ];
}
