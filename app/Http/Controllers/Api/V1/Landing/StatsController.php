<?php

namespace App\Http\Controllers\Api\V1\Landing;

use App\Http\Controllers\Controller;
use App\Models\AwardCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class StatsController extends Controller
{
    public const string CACHE_KEY = 'landing-api.stats';

    public const int CACHE_SECONDS = 300;

    /**
     * Registration totals for the landing page, counting every submission (disqualified included).
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, $this->stats(...)),
        ]);
    }

    /**
     * @return array{registered: int, papers_submitted: int, by_category: list<array{category_id: int, registered: int, papers_submitted: int}>, updated_at: string}
     */
    private function stats(): array
    {
        $byCategory = AwardCategory::query()
            ->withCount([
                'submissions as registered',
                'submissions as papers_submitted' => fn (Builder $query) => $query->whereNotNull('paper_uploaded_at'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (AwardCategory $category): array => [
                'category_id' => $category->id,
                'registered' => (int) $category->getAttribute('registered'),
                'papers_submitted' => (int) $category->getAttribute('papers_submitted'),
            ]);

        return [
            'registered' => (int) $byCategory->sum('registered'),
            'papers_submitted' => (int) $byCategory->sum('papers_submitted'),
            'by_category' => array_values($byCategory->all()),
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
