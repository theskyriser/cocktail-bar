<?php

namespace App\Models;

use App\Concerns\HasNormalisedName;
use Database\Factories\AlcoholFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alcohol extends Model
{
    /** @use HasFactory<AlcoholFactory> */
    use HasFactory;

    use HasNormalisedName;
}
