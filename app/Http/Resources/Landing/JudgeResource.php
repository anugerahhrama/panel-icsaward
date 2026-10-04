<?php

namespace App\Http\Resources\Landing;

use App\Models\AwardCategory;
use App\Models\Judge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public judge profile mirrored by the Landing repo. Sends the ids of the categories the judge actively judges
 * (the controller eager loads `categories` without recused ones), but never the account or the recusal flag.
 *
 * @mixin Judge
 */
class JudgeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, name: string, position: string, institution: string|null, bio: string|null, photo_url: string|null, landing_category_id: int|null, landing_category_ids: list<int>, sort_order: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'position' => $this->position,
            'institution' => $this->institution,
            'bio' => $this->bio,
            'photo_url' => $this->photo_url === null ? null : url($this->photo_url),
            'landing_category_id' => $this->landing_category_id,
            'landing_category_ids' => array_values($this->categories->map(fn (AwardCategory $category): int => $category->id)->all()),
            'sort_order' => $this->sort_order,
        ];
    }
}
