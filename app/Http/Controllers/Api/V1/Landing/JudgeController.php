<?php

namespace App\Http\Controllers\Api\V1\Landing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Landing\JudgeResource;
use App\Models\Judge;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JudgeController extends Controller
{
    /**
     * List every judge marked to be shown on the landing page.
     */
    public function __invoke(): AnonymousResourceCollection
    {
        return JudgeResource::collection(
            Judge::query()->where('show_on_landing', true)->orderBy('sort_order')->orderBy('id')->get(),
        );
    }
}
