<?php

namespace App\Http\Resources\Landing;

use App\Models\Judge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public judge profile mirrored by the Landing repo; never includes the account or category assignments.
 *
 * @mixin Judge
 */
class JudgeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, name: string, position: string, institution: string|null, bio: string|null, photo_url: string|null, landing_category_id: int|null, sort_order: int}
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
            'sort_order' => $this->sort_order,
        ];
    }
}
