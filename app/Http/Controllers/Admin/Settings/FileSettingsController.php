<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateFileSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class FileSettingsController extends Controller
{
    /**
     * Templates are public downloads, served through `Setting::publicFileUrl()`.
     */
    public const string DISK = 'public';

    /**
     * Upload fields; each is stored as `{field}_path` + `{field}_name` settings.
     *
     * @var list<string>
     */
    public const array TEMPLATES = ['submission_template', 'statement_letter_template'];

    /**
     * @var list<string>
     */
    private const array KEYS = [
        'paper_allowed_extensions',
        'paper_max_size_mb',
        'require_statement_letter',
        'terms_organization',
        'terms_individual',
    ];

    /**
     * Show the files & terms settings.
     */
    public function edit(): Response
    {
        $settings = Setting::many([
            ...self::KEYS,
            ...array_map(fn (string $template): string => "{$template}_name", self::TEMPLATES),
        ]);

        return Inertia::render('admin/settings/files', [
            'settings' => [
                'paper_allowed_extensions' => array_values(array_filter(array_map(trim(...), explode(',', $settings['paper_allowed_extensions'] ?? '')))),
                'paper_max_size_mb' => $settings['paper_max_size_mb'],
                'require_statement_letter' => $settings['require_statement_letter'] === '1',
                'terms_organization' => $settings['terms_organization'],
                'terms_individual' => $settings['terms_individual'],
            ],
            'templates' => collect(self::TEMPLATES)->mapWithKeys(fn (string $template): array => [
                $template => [
                    'url' => Setting::publicFileUrl("{$template}_path"),
                    'name' => $settings["{$template}_name"],
                ],
            ])->all(),
            'paperExtensionOptions' => UpdateFileSettingsRequest::PAPER_EXTENSION_OPTIONS,
        ]);
    }

    /**
     * Update the files & terms settings.
     *
     * New templates are stored before the transaction and removed again if it fails;
     * replaced or removed files are only deleted once the new values are saved.
     */
    public function update(UpdateFileSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $previousPaths = Setting::many(array_map(fn (string $template): string => "{$template}_path", self::TEMPLATES));

        /** @var array<string, array{path: string, name: string}> $stored */
        $stored = [];

        try {
            foreach (self::TEMPLATES as $template) {
                $file = $request->file($template);

                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $path = $file->store('settings', self::DISK);

                if ($path === false) {
                    throw new RuntimeException("Unable to store the {$template} file.");
                }

                $stored[$template] = ['path' => $path, 'name' => $file->getClientOriginalName()];
            }

            $obsoletePaths = DB::transaction(function () use ($request, $validated, $stored, $previousPaths): array {
                $obsoletePaths = [];

                foreach (self::TEMPLATES as $template) {
                    $isReplaced = isset($stored[$template]);

                    if (! $isReplaced && ! $request->boolean("remove_{$template}")) {
                        continue;
                    }

                    Setting::put("{$template}_path", $stored[$template]['path'] ?? null);
                    Setting::put("{$template}_name", $stored[$template]['name'] ?? null);

                    $previousPath = $previousPaths["{$template}_path"];

                    if ($previousPath !== null && $previousPath !== '') {
                        $obsoletePaths[] = $previousPath;
                    }
                }

                Setting::put('paper_allowed_extensions', implode(',', array_unique($validated['paper_allowed_extensions'])));
                Setting::put('paper_max_size_mb', (string) $validated['paper_max_size_mb']);
                Setting::put('require_statement_letter', $request->boolean('require_statement_letter') ? '1' : '0');
                Setting::put('terms_organization', $validated['terms_organization']);
                Setting::put('terms_individual', $validated['terms_individual']);

                return $obsoletePaths;
            });
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete(array_column($stored, 'path'));

            throw $exception;
        }

        Storage::disk(self::DISK)->delete($obsoletePaths);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'File & terms settings saved.']);

        return to_route('admin.settings.files.edit');
    }
}
