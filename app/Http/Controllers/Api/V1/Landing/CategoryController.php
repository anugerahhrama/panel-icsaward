<?php

namespace App\Http\Controllers\Api\V1\Landing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Landing\CategoryResource;
use App\Models\AwardCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * List every award category; the Landing repo removes categories missing from this list.
     */
    public function __invoke(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            AwardCategory::query()->orderBy('sort_order')->orderBy('id')->get(),
        );
    }
}
