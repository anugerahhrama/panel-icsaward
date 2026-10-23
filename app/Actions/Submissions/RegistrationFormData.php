<?php

namespace App\Actions\Submissions;

use App\Enums\ApplicantType;
use App\Models\AwardCategory;
use App\Models\Setting;
use Illuminate\Support\Collection;

class RegistrationFormData
{
    /**
     * Get the award categories offered in the "Initiative" step, each with its paper template download.
     *
     * @return Collection<int, array{id: int, name: string, description: string|null, applicant_type: ApplicantType, paper_template_url: string|null}>
     */
    public function categories(): Collection
    {
        $defaultPaperTemplateUrl = Setting::publicFileUrl('submission_template_path');

        return AwardCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'applicant_type', 'paper_template_path'])
            ->map(fn (AwardCategory $category): array => [
                ...$category->only(['id', 'name', 'description', 'applicant_type']),
                'paper_template_url' => $category->paper_template_url ?? $defaultPaperTemplateUrl,
            ]);
    }

    /**
     * Get the terms and conditions shown in the "Terms" step, per applicant type.
     *
     * @return array<string, mixed>
     */
    public function terms(): array
    {
        return [
            ApplicantType::Organization->value => Setting::get('terms_organization', ''),
            ApplicantType::Individual->value => Setting::get('terms_individual', ''),
        ];
    }
}
