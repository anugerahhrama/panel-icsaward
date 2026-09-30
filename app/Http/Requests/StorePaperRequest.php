<?php

namespace App\Http\Requests;

use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StorePaperRequest extends FormRequest
{
    /**
     * Statement Letters are scanned or photographed documents bearing a duty stamp.
     *
     * @var list<string>
     */
    public const array STATEMENT_LETTER_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $submission = $this->route('submission');

        return $submission instanceof Submission && $this->user()?->can('update', $submission) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKilobytes = self::maxSizeMb() * 1024;
        $paperFile = File::types(self::paperExtensions())->max($maxKilobytes);
        $statementLetterFile = File::types(self::STATEMENT_LETTER_EXTENSIONS)->max($maxKilobytes);

        if ($this->isRevision()) {
            return [
                'paper' => ['nullable', 'required_without:statement_letter', $paperFile],
                'statement_letter' => ['nullable', 'required_without:paper', $statementLetterFile],
            ];
        }

        return [
            'paper' => ['required', $paperFile],
            'statement_letter' => [
                self::requiresStatementLetter() ? 'required' : 'nullable',
                $statementLetterFile,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paper.required_without' => 'Upload at least one revised file.',
            'statement_letter.required_without' => 'Upload at least one revised file.',
        ];
    }

    /**
     * A revision only replaces the files the participant uploads again; the rest are kept.
     */
    private function isRevision(): bool
    {
        $submission = $this->route('submission');

        return $submission instanceof Submission && $submission->isRevisionOpen();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'paper' => 'submission paper',
            'statement_letter' => 'statement letter',
        ];
    }

    /**
     * @return list<string>
     */
    public static function paperExtensions(): array
    {
        $extensions = array_map(trim(...), explode(',', strtolower(Setting::get('paper_allowed_extensions', 'pdf,pptx') ?? '')));

        return array_values(array_filter($extensions, fn (string $extension): bool => $extension !== ''));
    }

    public static function maxSizeMb(): int
    {
        return max(1, (int) Setting::get('paper_max_size_mb', '20'));
    }

    public static function requiresStatementLetter(): bool
    {
        return Setting::get('require_statement_letter', '1') === '1';
    }
}
