<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Judging\AnnounceCategory;
use App\Enums\Announcement;
use App\Http\Controllers\Controller;
use App\Models\AwardCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    /**
     * List every category with the state of its three announcements.
     */
    public function index(): Response
    {
        $counts = ['finalists'];

        foreach (Announcement::cases() as $announcement) {
            $counts["finalists as {$announcement->value}_recipients_count"] = fn ($query) => $announcement === Announcement::Winners
                ? $query->whereNotNull('award')
                : $query;
            $counts["finalists as {$announcement->value}_sent_count"] = fn ($query) => $query
                ->whereNotNull($announcement->notifiedColumn())
                ->when($announcement === Announcement::Winners, fn ($query) => $query->whereNotNull('award'));
        }

        return Inertia::render('admin/announcements/index', [
            'categories' => AwardCategory::query()
                ->withCount($counts)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (AwardCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'finalists_count' => (int) $category->finalists_count,
                    'announcements' => collect(Announcement::cases())->mapWithKeys(fn (Announcement $announcement): array => [
                        $announcement->value => [
                            'announced_at' => $category->{$announcement->categoryColumn()}?->toIso8601String(),
                            'blocked_reason' => $announcement->blockedReason($category),
                            'recipients_count' => (int) $category->getAttribute("{$announcement->value}_recipients_count"),
                            'sent_count' => (int) $category->getAttribute("{$announcement->value}_sent_count"),
                        ],
                    ]),
                ]),
        ]);
    }

    /**
     * Make an announcement for a category and queue the emails to its recipients.
     */
    public function store(Request $request, AwardCategory $category, Announcement $announcement, AnnounceCategory $announce): RedirectResponse
    {
        try {
            $recipients = $announce->handle($category, $announcement, $request->user());
        } catch (ValidationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$announcement->label()} announced for {$category->name}. {$recipients} ".str('email')->plural($recipients).' queued.',
        ]);

        return back();
    }
}
