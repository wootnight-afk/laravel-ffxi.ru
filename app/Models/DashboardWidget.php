<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'title', 'type', 'sort_order', 'column_span', 'is_active', 'settings'])]
class DashboardWidget extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'column_span' => 'integer',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }
}
