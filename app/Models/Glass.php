<?php

namespace App\Models;

use Database\Factories\GlassFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Glass extends Model
{
    /** @use HasFactory<GlassFactory> */
    use HasFactory;
}
