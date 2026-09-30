<?php

namespace App\Actions\Categories;

use App\Models\AwardCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SaveCategory
{
    /**
     * Save an award category and its downloadable paper template.
     *
     * A new template is stored before the transaction and removed again if it fails; the
     * replaced or removed template is only deleted once the transaction has committed. The assessment
     * template cannot be swapped once judges have scored the category.
     *
     * @param  array{name: string, description: string|null, applicant_type: string, assessment_template_id: int|null, sort_order: int}  $data
     */
    public function handle(AwardCategory $category, array $data, ?UploadedFile $template = null, bool $removeTemplate = false): AwardCategory
    {
        $previousTemplate = $category->paper_template_path;
        $storedTemplate = null;

        if ($template !== null) {
            $storedTemplate = $template->store('categories', AwardCategory::PAPER_TEMPLATE_DISK);

            if ($storedTemplate === false) {
                throw new RuntimeException('Unable to store the paper template.');
            }
        }

        try {
            DB::transaction(function () use ($category, $data, $template, $storedTemplate, $removeTemplate): void {
                $category->fill($data);

                if ($category->exists && $category->isDirty('assessment_template_id') && $category->judgeScores()->exists()) {
                    throw ValidationException::withMessages([
                        'assessment_template_id' => 'This category already has judge scores, so its assessment template cannot be changed.',
                    ]);
                }

                if ($storedTemplate !== null || $removeTemplate) {
                    $category->paper_template_path = $storedTemplate;
                    $category->paper_template_name = $template?->getClientOriginalName();
                }

                $category->save();
            });
        } catch (Throwable $exception) {
            if ($storedTemplate !== null) {
                Storage::disk(AwardCategory::PAPER_TEMPLATE_DISK)->delete($storedTemplate);
            }

            throw $exception;
        }

        if ($previousTemplate !== null && $previousTemplate !== $category->paper_template_path) {
            Storage::disk(AwardCategory::PAPER_TEMPLATE_DISK)->delete($previousTemplate);
        }

        return $category;
    }
}
