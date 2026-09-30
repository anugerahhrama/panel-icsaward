import { Head, router } from '@inertiajs/react';
import { Check, Copy, Eye, KeyRound } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import LandingApiSettingsController from '@/actions/App/Http/Controllers/Admin/Settings/LandingApiSettingsController';
import { SettingsSection } from '@/components/admin/settings-section';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import { dashboard } from '@/routes/admin';
import { edit } from '@/routes/admin/settings/landing-api';

type Endpoint = { label: string; url: string };

type Props = {
    tokenHint: string | null;
    token?: string | null;
    endpoints: Endpoint[];
};

type PendingAction = 'generate' | 'revoke' | null;

function CopyButton({ value, label }: { value: string; label: string }) {
    const [copiedText, copy] = useClipboard();

    return (
        <Button
            type="button"
            variant="outline"
            size="icon"
            aria-label={`Copy ${label}`}
            onClick={async () => {
                if (await copy(value)) {
                    toast.success(`${label} copied`);
                } else {
                    toast.error(`Could not copy the ${label.toLowerCase()}.`);
                }
            }}
        >
            {copiedText === value ? <Check /> : <Copy />}
        </Button>
    );
}

function TokenValue({ token }: { token: string | null | undefined }) {
    const [loading, setLoading] = useState(false);

    if (token === undefined || token === null) {
        return (
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={loading}
                onClick={() => {
                    setLoading(true);
                    router.reload({
                        only: ['token'],
                        onFinish: () => setLoading(false),
                    });
                }}
            >
                {loading ? <Spinner /> : <Eye />}
                Show token
            </Button>
        );
    }

    return (
        <div className="flex max-w-2xl gap-2">
            <Input
                readOnly
                value={token}
                className="font-mono"
                aria-label="Landing API token"
            />
            <CopyButton value={token} label="Token" />
        </div>
    );
}

export default function LandingApiSettings({
    tokenHint,
    token,
    endpoints,
}: Props) {
    const [pendingAction, setPendingAction] = useState<PendingAction>(null);
    const [processing, setProcessing] = useState(false);
    const hasToken = tokenHint !== null;

    const confirm = () => {
        const action =
            pendingAction === 'revoke'
                ? LandingApiSettingsController.destroy()
                : LandingApiSettingsController.store();

        router.visit(action, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setPendingAction(null);
            },
        });
    };

    return (
        <>
            <Head title="Landing API" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="Landing API"
                    description="Read-only endpoints the landing site uses to mirror categories, judges and registration stats."
                />

                <SettingsSection
                    title="Access token"
                    description="Sent by the landing site as a bearer token. Without a token the API is disabled."
                    defaultOpen
                >
                    <div className="grid gap-4">
                        <div className="flex items-center gap-2 text-sm">
                            <KeyRound className="size-4 text-muted-foreground" />
                            {hasToken ? (
                                <span>
                                    Active token ending in{' '}
                                    <code className="font-mono">
                                        {tokenHint}
                                    </code>
                                </span>
                            ) : (
                                <span className="text-muted-foreground">
                                    No token yet. The API rejects every request.
                                </span>
                            )}
                        </div>

                        {hasToken && <TokenValue token={token} />}

                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                onClick={() => setPendingAction('generate')}
                            >
                                {hasToken
                                    ? 'Regenerate token'
                                    : 'Generate token'}
                            </Button>
                            {hasToken && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setPendingAction('revoke')}
                                >
                                    Revoke
                                </Button>
                            )}
                        </div>
                    </div>
                </SettingsSection>

                <SettingsSection
                    title="Endpoints"
                    description="GET only, limited to 60 requests per minute. Each response is wrapped in a data key."
                    defaultOpen
                >
                    <div className="grid gap-3">
                        {endpoints.map((endpoint) => (
                            <div key={endpoint.url} className="grid gap-1">
                                <span className="text-sm font-medium">
                                    {endpoint.label}
                                </span>
                                <div className="flex max-w-2xl gap-2">
                                    <Input
                                        readOnly
                                        value={endpoint.url}
                                        className="font-mono"
                                        aria-label={`${endpoint.label} endpoint`}
                                    />
                                    <CopyButton
                                        value={endpoint.url}
                                        label="URL"
                                    />
                                </div>
                            </div>
                        ))}
                        <p className="text-sm text-muted-foreground">
                            Header:{' '}
                            <code className="font-mono">
                                Authorization: Bearer &lt;token&gt;
                            </code>
                        </p>
                    </div>
                </SettingsSection>
            </div>

            <Dialog
                open={pendingAction !== null}
                onOpenChange={(open) => !open && setPendingAction(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        {pendingAction === 'revoke'
                            ? 'Revoke token?'
                            : hasToken
                              ? 'Regenerate token?'
                              : 'Generate token?'}
                    </DialogTitle>
                    <DialogDescription>
                        {pendingAction === 'revoke'
                            ? 'The landing site stops syncing until a new token is generated and set as ASSESSMENT_API_TOKEN.'
                            : hasToken
                              ? 'The current token stops working immediately. Update ASSESSMENT_API_TOKEN on the landing site with the new one.'
                              : 'Set the new token as ASSESSMENT_API_TOKEN on the landing site.'}
                    </DialogDescription>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant={
                                pendingAction === 'revoke'
                                    ? 'destructive'
                                    : 'default'
                            }
                            disabled={processing}
                            onClick={confirm}
                        >
                            {processing && <Spinner />}
                            {pendingAction === 'revoke' ? 'Revoke' : 'Confirm'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

LandingApiSettings.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Landing API', href: edit() },
    ],
};
