<?php

namespace App\Models;

use App\Cocktails\Path;
use App\Enums\IngredientKind;
use App\Enums\IngredientType;
use App\Enums\MeasurementUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $slug
 * @property string $name
 * @property IngredientKind $kind
 * @property IngredientType $type
 * @property bool $is_alcoholic
 * @property MeasurementUnit $default_unit
 * @property Path $path
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ingredient|null $parent
 * @property-read Collection<int, Ingredient> $children
 * @property-read Collection<int, IngredientAlias> $aliases
 * @property-read Collection<int, Ingredient> $substitutes
 * @property-read Collection<int, Ingredient> $substituteFor
 * @property-read User|null $creator
 * @property-read Collection<int, Recipe> $recipes
 */
#[Fillable(['parent_id', 'slug', 'name', 'kind', 'type', 'is_alcoholic', 'default_unit', 'created_by_user_id'])]
class Ingredient extends Model
{
    /**
     * The path is derived from the parent's path and never assigned by a
     * caller, so that one rule produces it on every write path.
     */
    protected static function booted(): void
    {
        static::saving(function (Ingredient $ingredient): void {
            if ($ingredient->isDirty('parent_id')) {
                $ingredient->unsetRelation('parent');
            }

            $parent = $ingredient->parent;

            $ingredient->path = $parent instanceof Ingredient
                ? $parent->path->append($ingredient->slug)
                : Path::root($ingredient->slug);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => IngredientKind::class,
            'type' => IngredientType::class,
            'is_alcoholic' => 'boolean',
            'default_unit' => MeasurementUnit::class,
            'path' => Path::class,
        ];
    }

    /**
     * Move this ingredient under a new parent, rewriting the paths of every
     * descendant. Only the curated tree ever moves, so the depth-first saves
     * are cheaper than keeping a second path implementation in SQL.
     */
    public function reparent(?Ingredient $parent): void
    {
        $this->parent()->associate($parent);
        $this->save();

        $this->children()->get()->each(fn (Ingredient $child) => $child->reparent($this));
    }

    /**
     * @return BelongsTo<Ingredient, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Ingredient, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<IngredientAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(IngredientAlias::class);
    }

    /**
     * @return BelongsToMany<Ingredient, $this, IngredientSubstitute>
     */
    public function substitutes(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'ingredient_substitute', 'from_ingredient_id', 'to_ingredient_id')
            ->using(IngredientSubstitute::class)
            ->withPivot(['quality', 'note']);
    }

    /**
     * @return BelongsToMany<Ingredient, $this, IngredientSubstitute>
     */
    public function substituteFor(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'ingredient_substitute', 'to_ingredient_id', 'from_ingredient_id')
            ->using(IngredientSubstitute::class)
            ->withPivot(['quality', 'note']);
    }

    /**
     * @return BelongsToMany<Recipe, $this, RecipeIngredient>
     */
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_ingredient')
            ->using(RecipeIngredient::class)
            ->withPivot(['amount', 'is_optional', 'sub_rule', 'role', 'position']);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @param  Builder<Ingredient>  $query
     */
    #[Scope]
    protected function alcoholic(Builder $query): void
    {
        $query->where('is_alcoholic', true);
    }

    /**
     * @param  Builder<Ingredient>  $query
     */
    #[Scope]
    protected function ofType(Builder $query, IngredientType $type): void
    {
        $query->where('type', $type);
    }

    /**
     * @param  Builder<Ingredient>  $query
     */
    #[Scope]
    protected function curated(Builder $query): void
    {
        $query->whereNull('created_by_user_id');
    }

    /**
     * @param  Builder<Ingredient>  $query
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

    /**
     * @param  Builder<Ingredient>  $query
     */
    #[Scope]
    protected function within(Builder $query, Path $path): void
    {
        $query->where(function (Builder $nested) use ($path): void {
            $nested->where('path', $path->value)
                ->orWhere('path', 'like', $path->descendantPattern());
        });
    }
}
