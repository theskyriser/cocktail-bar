<?php

namespace App\Models;

use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A recipe owns its prose only. Every measured line lives in the
 * recipe/ingredient pivot, so no quantity or unit belongs on this table.
 *
 * @property int $id
 * @property int|null $glass_id
 * @property string $name
 * @property string|null $description
 * @property string|null $method
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Glass|null $glass
 * @property-read User|null $creator
 * @property-read Collection<int, RecipeIngredient> $items
 * @property-read Collection<int, Ingredient> $ingredients
 * @property-read Collection<int, Tag> $tags
 */
#[Fillable(['glass_id', 'name', 'description', 'method', 'created_by_user_id'])]
class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Glass, $this>
     */
    public function glass(): BelongsTo
    {
        return $this->belongsTo(Glass::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<RecipeIngredient, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Ingredient, $this, RecipeIngredient>
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'recipe_ingredient')
            ->using(RecipeIngredient::class)
            ->withPivot(['amount', 'is_optional', 'sub_rule', 'role', 'position'])
            ->orderBy('recipe_ingredient.position');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /**
     * @param  Builder<Recipe>  $query
     */
    #[Scope]
    protected function curated(Builder $query): void
    {
        $query->whereNull('created_by_user_id');
    }

    /**
     * @param  Builder<Recipe>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?User $user): void
    {
        $query->where(function (Builder $nested) use ($user): void {
            $nested->whereNull('created_by_user_id');

            if ($user instanceof User) {
                $nested->orWhere('created_by_user_id', $user->id);
            }
        });
    }
}
