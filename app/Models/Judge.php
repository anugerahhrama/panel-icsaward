<?php

namespace App\Models;

use Database\Factories\JudgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $position
 * @property string|null $institution
 * @property string|null $bio
 * @property string|null $photo_path
 * @property bool $show_on_landing
 * @property int|null $landing_category_id
 * @property int $sort_order
 * @property string|null $account_password
 * @property-read string|null $photo_url
 * @property-read int|null $categories_count
 * @property-read int|null $recused_categories_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'position', 'institution', 'bio', 'photo_path', 'show_on_landing', 'landing_category_id', 'sort_order'])]
#[Hidden(['account_password'])]
class Judge extends Model
{
    /** @use HasFactory<JudgeFactory> */
    use HasFactory;

    /**
     * Judge photos are shown on the public landing page.
     */
    public const string PHOTO_DISK = 'public';

    /**
     * The login account of this judge, if one has been created.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The category label shown next to the judge on the landing page; not an assignment.
     *
     * @return BelongsTo<AwardCategory, $this>
     */
    public function landingCategory(): BelongsTo
    {
        return $this->belongsTo(AwardCategory::class, 'landing_category_id');
    }

    /**
     * The categories this judge is assigned to score, including recused ones.
     *
     * @return BelongsToMany<AwardCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(AwardCategory::class, 'category_judges')
            ->withPivot('is_recused')
            ->withTimestamps();
    }

    /**
     * The scores this judge has given, across submissions and stages.
     *
     * @return HasMany<JudgeScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(JudgeScore::class);
    }

    /**
     * The ids of the categories whose submissions this judge has scored.
     *
     * @return Collection<int, int>
     */
    public function scoredCategoryIds(): Collection
    {
        return Submission::query()
            ->whereHas('judgeScores', fn (Builder $query) => $query->where('judge_id', $this->id))
            ->distinct()
            ->pluck('award_category_id');
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->photo_path === null ? null : Storage::disk(self::PHOTO_DISK)->url($this->photo_path),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'show_on_landing' => 'boolean',
            'sort_order' => 'integer',
            'account_password' => 'encrypted',
        ];
    }
}
