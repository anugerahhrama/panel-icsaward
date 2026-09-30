<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class AdminAccountRequest extends FormRequest
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
        $account = $this->route('account');
        $accountId = $account instanceof User ? $account->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($accountId)],
            'position' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::enum(UserRole::class)->only(UserRole::adminRoles())],
            'password' => [$accountId === null ? 'required' : 'nullable', 'string', Password::default(), 'confirmed'],
        ];
    }

    /**
     * Point to Restore when the email belongs to a deleted account, instead of "already taken".
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->has('email')) {
                    return;
                }

                if (User::onlyTrashed()->where('email', $this->string('email')->toString())->exists()) {
                    $validator->errors()->forget('email');
                    $validator->errors()->add('email', 'This email belongs to a deleted account. Restore it from the Deleted tab instead.');
                }
            },
        ];
    }

    /**
     * The validated account fields.
     *
     * @return array{name: string, email: string, position: string|null, phone: string|null, role: UserRole, password: string|null}
     */
    public function accountData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'email' => $this->string('email')->toString(),
            'position' => $this->filled('position') ? $this->string('position')->toString() : null,
            'phone' => $this->filled('phone') ? $this->string('phone')->toString() : null,
            'role' => UserRole::from($this->string('role')->toString()),
            'password' => $this->filled('password') ? $this->string('password')->toString() : null,
        ];
    }
}
