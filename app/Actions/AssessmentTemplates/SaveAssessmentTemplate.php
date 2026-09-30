<?php

namespace App\Actions\AssessmentTemplates;

use App\Models\AssessmentTemplate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveAssessmentTemplate
{
    /**
     * Create or update a template and sync its scoring criteria.
     *
     * Criteria are matched by `id` instead of being recreated, so a criterion keeps its id across edits
     * (judge scores will reference it). Rows missing from the payload are deleted and `sort_order`
     * follows the payload order. Criteria that judges have already scored cannot be removed.
     *
     * @param  array{name: string, description: string|null, criteria: list<array{id: int|null, aspect: string, criteria: string, description: string|null, weight: int}>}  $data
     */
    public function handle(AssessmentTemplate $template, array $data): AssessmentTemplate
    {
        return DB::transaction(function () use ($template, $data): AssessmentTemplate {
            $template->fill(Arr::only($data, ['name', 'description']))->save();

            $keptIds = collect($data['criteria'])->pluck('id')->filter()->all();

            $removed = $template->criteria()->whereNotIn('id', $keptIds);

            if ((clone $removed)->whereHas('scores')->exists()) {
                throw ValidationException::withMessages([
                    'criteria' => 'Criteria that already have judge scores cannot be removed.',
                ]);
            }

            $removed->delete();

            $existing = $template->criteria()->get()->keyBy('id');

            foreach ($data['criteria'] as $index => $criterion) {
                $attributes = [
                    'aspect' => $criterion['aspect'],
                    'criteria' => $criterion['criteria'],
                    'description' => $criterion['description'],
                    'weight' => $criterion['weight'],
                    'sort_order' => $index + 1,
                ];

                $current = $criterion['id'] === null ? null : $existing->get($criterion['id']);

                if ($current === null) {
                    $template->criteria()->create($attributes);
                } else {
                    $current->update($attributes);
                }
            }

            return $template;
        });
    }
}
