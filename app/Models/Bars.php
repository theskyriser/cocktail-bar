<?php

namespace App\Models;

use Database\Factories\BarsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bars extends Model
{
    /** @use HasFactory<BarsFactory> */
    use HasFactory;
}
