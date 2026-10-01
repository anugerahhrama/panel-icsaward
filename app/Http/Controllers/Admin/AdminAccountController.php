<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Accounts\DeleteAdminAccount;
use App\Actions\Accounts\SaveAdminAccount;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminAccountController extends Controller
{
    private const COLUMNS = ['id', 'name', 'email', 'position', 'phone', 'role', 'account_password', 'created_at', 'deleted_at'];

    /**
     * List the active and deleted admin and superadmin accounts.
     *
     * The stored password itself is only sent through the optional `revealedPassword` prop,
     * for the account named in the `reveal` query.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('admin/accounts/index', [
            'accounts' => User::query()
                ->whereIn('role', UserRole::adminRoles())
                ->orderBy('name')
                ->get(self::COLUMNS)
                ->map($this->accountRow(...)),
            'deletedAccounts' => User::onlyTrashed()
                ->whereIn('role', UserRole::adminRoles())
                ->latest('deleted_at')
                ->get(self::COLUMNS)
                ->map($this->accountRow(...)),
            'revealedPassword' => Inertia::optional(fn (): ?array => $this->revealAccountPassword($request)),
        ]);
    }

    /**
     * Create an admin or superadmin account.
     */
    public function store(AdminAccountRequest $request, SaveAdminAccount $saveAdminAccount): RedirectResponse
    {
        $account = $saveAdminAccount->handle($request->user(), new User, $request->accountData());

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$account->name}\" created."]);

        return to_route('admin.accounts.index');
    }

    /**
     * Update an admin or superadmin account, resetting its password when a new one is given.
     */
    public function update(AdminAccountRequest $request, User $account, SaveAdminAccount $saveAdminAccount): RedirectResponse
    {
        $this->ensureAdminAccount($account);

        $saveAdminAccount->handle($request->user(), $account, $request->accountData());

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$account->name}\" updated."]);

        return to_route('admin.accounts.index');
    }

    /**
     * Soft delete an admin or superadmin account.
     */
    public function destroy(Request $request, User $account, DeleteAdminAccount $deleteAdminAccount): RedirectResponse
    {
        $this->ensureAdminAccount($account);

        try {
            $deleteAdminAccount->handle($request->user(), $account);
        } catch (ValidationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return to_route('admin.accounts.index');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$account->name}\" deleted."]);

        return to_route('admin.accounts.index');
    }

    /**
     * Restore a deleted admin or superadmin account so it can sign in again.
     */
    public function restore(User $account): RedirectResponse
    {
        $this->ensureAdminAccount($account);

        if ($account->trashed()) {
            $account->restore();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$account->name}\" restored."]);

        return to_route('admin.accounts.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function accountRow(User $account): array
    {
        return [
            ...$account->toArray(),
            'has_account_password' => $account->getRawOriginal('account_password') !== null,
        ];
    }

    /**
     * The password last set from Admin Accounts for the account in the `reveal` query, for superadmins only.
     *
     * The stored copy is discarded once the account owner has changed their own password.
     *
     * @return array{account_id: int, password: string|null}|null
     */
    private function revealAccountPassword(Request $request): ?array
    {
        if ($request->user()?->role !== UserRole::Superadmin) {
            return null;
        }

        $account = User::query()
            ->whereIn('role', UserRole::adminRoles())
            ->find($request->integer('reveal'));

        if ($account === null) {
            return null;
        }

        if ($account->account_password !== null && ! Hash::check($account->account_password, $account->password)) {
            $account->forceFill(['account_password' => null])->save();
        }

        return ['account_id' => $account->id, 'password' => $account->account_password];
    }

    /**
     * Judge and participant accounts are managed elsewhere, never from Admin Accounts.
     */
    private function ensureAdminAccount(User $account): void
    {
        abort_unless($account->role->isAdmin(), 404);
    }
}
