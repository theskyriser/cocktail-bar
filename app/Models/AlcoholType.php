<?php

namespace App\Models;

use App\Enums\AlcoholParentType;
use Database\Factories\AlcoholTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlcoholType extends Model
{
    /** @use HasFactory<AlcoholTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_type' => AlcoholParentType::class,
        ];
    }
}
