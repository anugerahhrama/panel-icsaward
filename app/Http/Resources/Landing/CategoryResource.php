<?php

namespace App\Http\Resources\Landing;

use App\Models\AwardCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Award category mirrored by the Landing repo.
 *
 * @mixin AwardCategory
 */
class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, name: string, description: string|null, applicant_type: string, sort_order: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'applicant_type' => $this->applicant_type->value,
            'sort_order' => $this->sort_order,
        ];
    }
}
