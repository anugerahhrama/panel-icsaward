import { useHttp } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import type { MouseEvent } from 'react';
import { useState } from 'react';
import EmailPreviewController from '@/actions/App/Http/Controllers/Admin/Settings/EmailPreviewController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';

type PreviewForm = {
    template: string;
    subject: string;
    body: string;
    contact_email: string;
};

type Preview = { subject: string; html: string };

/**
 * Renders the template from the unsaved form values with sample data. Nothing is saved or sent.
 */
export function EmailPreviewDialog({
    template,
    title,
}: {
    template: string;
    title: string;
}) {
    const [open, setOpen] = useState(false);
    const [failed, setFailed] = useState(false);
    const http = useHttp<PreviewForm, Preview>({
        template,
        subject: '',
        body: '',
        contact_email: '',
    });

    function showPreview(event: MouseEvent<HTMLButtonElement>) {
        const form = event.currentTarget.form;

        if (form === null) {
            return;
        }

        const values = new FormData(form);
        const field = (name: string) => {
            const value = values.get(name);

            return typeof value === 'string' ? value : '';
        };

        setFailed(false);
        setOpen(true);
        http.clearErrors();
        http.transform(() => ({
            template,
            subject: field(`${template}_email_subject`),
            body: field(`${template}_email_body`),
            contact_email: field('contact_email'),
        }));
        void http.post(EmailPreviewController.url(), {
            onHttpException: () => setFailed(true),
            onNetworkError: () => setFailed(true),
        });
    }

    const errors = Object.values(http.errors).filter(Boolean);
    const preview =
        http.processing || errors.length > 0 || failed ? null : http.response;

    return (
        <>
            <Button
                type="button"
                variant="outline"
                className="w-fit"
                onClick={showPreview}
            >
                <Eye />
                Preview
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="flex h-[85vh] flex-col sm:max-w-3xl">
                    <DialogHeader className="pr-8">
                        <DialogTitle>Preview: {title}</DialogTitle>
                        <DialogDescription>
                            Rendered from the current form with sample data —
                            not saved or sent.
                        </DialogDescription>
                    </DialogHeader>

                    {errors.length > 0 || failed ? (
                        <div className="grid gap-1">
                            {errors.map((message) => (
                                <InputError key={message} message={message} />
                            ))}
                            {failed && (
                                <InputError message="The preview could not be loaded. Please try again." />
                            )}
                        </div>
                    ) : preview === null ? (
                        <div className="flex min-h-0 flex-1 flex-col gap-3">
                            <Skeleton className="h-5 w-2/3" />
                            <Skeleton className="min-h-0 flex-1" />
                        </div>
                    ) : (
                        <div className="flex min-h-0 flex-1 flex-col gap-3">
                            <p className="text-sm">
                                <span className="text-muted-foreground">
                                    Subject:{' '}
                                </span>
                                <span className="font-medium">
                                    {preview.subject}
                                </span>
                            </p>
                            <iframe
                                title={`Preview: ${title}`}
                                sandbox=""
                                srcDoc={preview.html}
                                className="min-h-0 w-full flex-1 rounded-md border bg-white"
                            />
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
