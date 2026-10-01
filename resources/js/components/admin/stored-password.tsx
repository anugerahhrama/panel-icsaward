import { router } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useState } from 'react';
import { CopyPasswordButton } from '@/components/admin/copy-password-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

/**
 * A password stored for superadmins, loaded on demand through a partial reload of the `prop` prop.
 * `value` is undefined until loaded, and null once the owner has changed the password.
 */
export function StoredPassword({
    value,
    prop,
    data,
    changedMessage,
}: {
    value: string | null | undefined;
    prop: string;
    data?: Record<string, string | number>;
    changedMessage: string;
}) {
    const [loading, setLoading] = useState(false);

    if (value === undefined) {
        return (
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={loading}
                onClick={() => {
                    setLoading(true);
                    router.reload({
                        only: [prop],
                        data,
                        preserveUrl: true,
                        onFinish: () => setLoading(false),
                    });
                }}
            >
                {loading ? <Spinner /> : <Eye />}
                Show password
            </Button>
        );
    }

    if (value === null) {
        return (
            <p className="text-sm text-muted-foreground">{changedMessage}</p>
        );
    }

    return (
        <div className="flex gap-2">
            <Input
                readOnly
                value={value}
                className="font-mono"
                aria-label="Current password"
            />
            <CopyPasswordButton value={value} />
        </div>
    );
}
