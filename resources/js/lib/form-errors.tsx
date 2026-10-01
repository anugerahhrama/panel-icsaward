import { toast } from 'sonner';

/**
 * Toaster mounted by `AuthLayout` (bottom-center) for auth & submission pages.
 */
export const FORM_ERROR_TOASTER_ID = 'form-errors';

/**
 * Show every validation message of one submit as a single combined toast.
 * A fixed toast id replaces the previous toast instead of stacking them.
 */
export function toastFormErrors(
    errors: Partial<Record<string, string | undefined>>,
): void {
    const messages = [
        ...new Set(
            Object.values(errors).filter(
                (message): message is string =>
                    typeof message === 'string' && message !== '',
            ),
        ),
    ];

    if (messages.length === 0) {
        return;
    }

    const options = { id: 'form-errors', toasterId: FORM_ERROR_TOASTER_ID };

    if (messages.length === 1) {
        toast.error(messages[0], options);

        return;
    }

    toast.error('Please fix the following:', {
        ...options,
        description: (
            <ul className="list-disc space-y-0.5 pl-4">
                {messages.map((message) => (
                    <li key={message}>{message}</li>
                ))}
            </ul>
        ),
    });
}
