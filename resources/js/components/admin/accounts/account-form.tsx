import { useForm } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { CopyPasswordButton } from '@/components/admin/copy-password-button';
import { SaveButton } from '@/components/admin/save-button';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { generatePassword } from '@/lib/password';
import {
    ADMIN_ROLE_LABELS,
    type AccountRow,
    type AdminRole,
} from '@/pages/admin/accounts/columns';
import { store, update } from '@/routes/admin/accounts';

type AccountFormProps = {
    account?: AccountRow;
    isSelf: boolean;
    onSaved?: () => void;
};

export function AccountForm({ account, isSelf, onSaved }: AccountFormProps) {
    const [isPasswordVisible, setPasswordVisible] = useState(false);
    const { data, setData, post, put, processing, errors } = useForm<{
        name: string;
        email: string;
        position: string;
        phone: string;
        role: AdminRole;
        password: string;
        password_confirmation: string;
    }>({
        name: account?.name ?? '',
        email: account?.email ?? '',
        position: account?.position ?? '',
        phone: account?.phone ?? '',
        role: account?.role ?? 'admin',
        password: '',
        password_confirmation: '',
    });

    const fillGeneratedPassword = () => {
        const password = generatePassword();

        setData((current) => ({
            ...current,
            password,
            password_confirmation: password,
        }));
        setPasswordVisible(true);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => onSaved?.(),
            onError: () =>
                toast.error('Failed to save account. Check the fields below.'),
        };

        if (account) {
            put(update(account.id).url, options);
        } else {
            post(store().url, options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    value={data.name}
                    maxLength={255}
                    required
                    onChange={(e) => setData('name', e.target.value)}
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    value={data.email}
                    maxLength={255}
                    required
                    autoComplete="off"
                    onChange={(e) => setData('email', e.target.value)}
                />
                <InputError message={errors.email} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="position">Position</Label>
                <Input
                    id="position"
                    value={data.position}
                    maxLength={255}
                    onChange={(e) => setData('position', e.target.value)}
                />
                <InputError message={errors.position} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="phone">Phone</Label>
                <Input
                    id="phone"
                    type="tel"
                    value={data.phone}
                    maxLength={30}
                    onChange={(e) => setData('phone', e.target.value)}
                />
                <InputError message={errors.phone} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="role">Role</Label>
                <Select
                    value={data.role}
                    disabled={isSelf}
                    onValueChange={(value) =>
                        setData('role', value as AdminRole)
                    }
                >
                    <SelectTrigger id="role" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.entries(ADMIN_ROLE_LABELS).map(
                            ([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ),
                        )}
                    </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                    {isSelf
                        ? 'You cannot change your own role.'
                        : 'Superadmins can also manage admin accounts and system settings.'}
                </p>
                <InputError message={errors.role} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="password">
                    {account ? 'New password' : 'Password'}
                </Label>
                <div className="flex gap-2">
                    <div className="flex-1">
                        <PasswordInput
                            id="password"
                            value={data.password}
                            required={!account}
                            autoComplete="new-password"
                            className="font-mono"
                            visible={isPasswordVisible}
                            onVisibleChange={setPasswordVisible}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                        />
                    </div>
                    <CopyPasswordButton value={data.password} />
                    <Button
                        type="button"
                        variant="outline"
                        onClick={fillGeneratedPassword}
                    >
                        <KeyRound />
                        Generate
                    </Button>
                </div>
                <p className="text-xs text-muted-foreground">
                    {account
                        ? 'Leave blank to keep the current password. '
                        : ''}
                    Copy the password before saving — it cannot be viewed again.
                </p>
                <InputError message={errors.password} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    value={data.password_confirmation}
                    required={!account || data.password !== ''}
                    autoComplete="new-password"
                    className="font-mono"
                    visible={isPasswordVisible}
                    onVisibleChange={setPasswordVisible}
                    onChange={(e) =>
                        setData('password_confirmation', e.target.value)
                    }
                />
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
