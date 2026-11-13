<?php

namespace App\Models;

use App\Enums\SubstitutionQuality;
use Database\Factories\SubstitutionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Substitution extends Model
{
    /** @use HasFactory<SubstitutionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quality' => SubstitutionQuality::class,
        ];
    }
}
