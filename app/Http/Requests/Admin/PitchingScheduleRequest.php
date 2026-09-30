<?php

namespace App\Http\Requests\Admin;

use App\Actions\Judging\ConfirmFinalists;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PitchingScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Dates and times are entered in WIB (`Setting::EVENT_TIMEZONE`).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255', 'required_without:meeting_link'],
            'meeting_link' => ['nullable', 'url', 'max:500'],
            'slots' => ['present', 'array', 'max:'.ConfirmFinalists::MAX_FINALISTS],
            'slots.*.submission_id' => ['required', 'integer', 'distinct'],
            'slots.*.starts_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Reject slots that start before the session or share a start time.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $schedule = $this->scheduleData();
                $taken = [];

                foreach ($schedule['slots'] as $index => $slot) {
                    if ($slot['starts_at'] === null) {
                        continue;
                    }

                    if ($slot['starts_at'] < $schedule['start_time']) {
                        $validator->errors()->add("slots.{$index}.starts_at", 'The slot cannot start before the session.');
                    } elseif (in_array($slot['starts_at'], $taken, true)) {
                        $validator->errors()->add("slots.{$index}.starts_at", 'Another finalist already pitches at this time.');
                    }

                    $taken[] = $slot['starts_at'];
                }
            },
        ];
    }

    /**
     * The validated schedule; a slot without a start time is not scheduled yet.
     *
     * @return array{date: string, start_time: string, location: string|null, meeting_link: string|null, slots: list<array{submission_id: int, starts_at: string|null}>}
     */
    public function scheduleData(): array
    {
        return [
            'date' => $this->string('date')->toString(),
            'start_time' => $this->string('start_time')->toString(),
            'location' => $this->filled('location') ? $this->string('location')->toString() : null,
            'meeting_link' => $this->filled('meeting_link') ? $this->string('meeting_link')->toString() : null,
            'slots' => array_map(fn (int|string $index): array => [
                'submission_id' => $this->integer("slots.{$index}.submission_id"),
                'starts_at' => $this->filled("slots.{$index}.starts_at") ? $this->string("slots.{$index}.starts_at")->toString() : null,
            ], array_keys($this->array('slots'))),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'start_time' => 'start time',
            'meeting_link' => 'meeting link',
            'slots.*.starts_at' => 'start time',
        ];
    }
}
