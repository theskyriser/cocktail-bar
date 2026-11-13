<?php

namespace App\Models;

use Database\Factories\BarStockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BarStock extends Model
{
    /** @use HasFactory<BarStockFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'bottle_size' => 'integer',
        ];
    }
}
