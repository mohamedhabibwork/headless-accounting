<?php

declare(strict_types=1);

namespace Headless\Accounting\Models;

use Headless\Accounting\Support\Config;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    /**
     * Suffix appended to the configured table_prefix to form the full table name.
     * Subclasses MUST override this with their table suffix (e.g. 'accounts', 'orders').
     */
    protected string $tableSuffix = '';

    public function getTable(): string
    {
        return Config::string('headless-accounting.table_prefix', 'ha_').$this->tableSuffix;
    }
}
