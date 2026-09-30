<?php

namespace App\Http\Requests\Admin;

use App\Models\AwardCategory;
use App\Models\Judge;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class JudgeRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $judge = $this->route('judge');
        $account = $judge instanceof Judge ? $judge->user_id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'remove_photo' => ['boolean'],
            'show_on_landing' => ['boolean'],
            'landing_category_id' => ['nullable', 'integer', Rule::exists(AwardCategory::class, 'id')],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'has_account' => $account === null ? ['boolean'] : ['accepted'],
            'email' => ['exclude_unless:has_account,true,1', 'required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($account)],
            'password' => ['exclude_unless:has_account,true,1', $account === null ? 'required' : 'nullable', 'string', Password::default(), 'confirmed'],
            'assignments' => ['array'],
            'assignments.*.award_category_id' => ['required', 'integer', 'distinct', Rule::exists(AwardCategory::class, 'id')],
            'assignments.*.is_recused' => ['boolean'],
        ];
    }

    /**
     * Keep the category assignments unchanged while the judging setup is locked for the user.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $access = Gate::inspect('manage-judging-setup');

                if ($access->allowed() || $validator->errors()->has('assignments*')) {
                    return;
                }

                if ($this->assignmentData() != $this->currentAssignments()) {
                    $validator->errors()->add('assignments', (string) $access->message());
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'has_account.accepted' => 'A judge’s login account cannot be removed here.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'landing_category_id' => 'landing category',
            'assignments.*.award_category_id' => 'category',
        ];
    }

    /**
     * The validated profile fields of the judge.
     *
     * @return array{name: string, position: string, institution: string|null, bio: string|null, show_on_landing: bool, landing_category_id: int|null, sort_order: int}
     */
    public function profileData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'position' => $this->string('position')->toString(),
            'institution' => $this->filled('institution') ? $this->string('institution')->toString() : null,
            'bio' => $this->filled('bio') ? $this->string('bio')->toString() : null,
            'show_on_landing' => $this->boolean('show_on_landing'),
            'landing_category_id' => $this->filled('landing_category_id') ? $this->integer('landing_category_id') : null,
            'sort_order' => $this->integer('sort_order'),
        ];
    }

    /**
     * The login account to create or update, or null when the judge has no account.
     *
     * @return array{email: string, password: string|null}|null
     */
    public function accountData(): ?array
    {
        if (! $this->boolean('has_account')) {
            return null;
        }

        return [
            'email' => $this->string('email')->toString(),
            'password' => $this->filled('password') ? $this->string('password')->toString() : null,
        ];
    }

    /**
     * The categories the judge is assigned to, keyed by category id.
     *
     * @return array<int, array{is_recused: bool}>
     */
    public function assignmentData(): array
    {
        $assignments = [];

        foreach (array_keys($this->array('assignments')) as $index) {
            $assignments[$this->integer("assignments.{$index}.award_category_id")] = [
                'is_recused' => $this->boolean("assignments.{$index}.is_recused"),
            ];
        }

        return $assignments;
    }

    /**
     * The judge's saved category assignments, in the shape of `assignmentData()`.
     *
     * @return array<int, array{is_recused: bool}>
     */
    private function currentAssignments(): array
    {
        $judge = $this->route('judge');

        if (! $judge instanceof Judge) {
            return [];
        }

        return $judge->categories()->get(['award_categories.id'])
            ->mapWithKeys(fn (AwardCategory $category): array => [
                $category->id => ['is_recused' => (bool) $category->getRelationValue('pivot')?->getAttribute('is_recused')],
            ])
            ->all();
    }

    /**
     * The newly uploaded photo, if any.
     */
    public function photo(): ?UploadedFile
    {
        $photo = $this->file('photo');

        return $photo instanceof UploadedFile ? $photo : null;
    }
}
