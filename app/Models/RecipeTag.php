<?php

namespace App\Models;

use Database\Factories\RecipeTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeTag extends Model
{
    /** @use HasFactory<RecipeTagFactory> */
    use HasFactory;
}
