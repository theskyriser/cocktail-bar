<?php

namespace App\Models;

use Database\Factories\AlcoholStockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlcoholStock extends Model
{
    /** @use HasFactory<AlcoholStockFactory> */
    use HasFactory;
}
