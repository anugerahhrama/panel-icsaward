import { LockIcon } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/**
 * Message shown on controls that are locked while a judging stage is active.
 */
export const JUDGING_LOCKED_REASON =
    'Locked while judging is in progress. Only a superadmin can change this.';

/**
 * Banner for admins on setup pages (categories, templates, judges) while a judging stage is active.
 */
export function JudgingLockAlert({ locked }: { locked: boolean }) {
    if (!locked) {
        return null;
    }

    return (
        <Alert>
            <LockIcon />
            <AlertTitle>Judging is in progress</AlertTitle>
            <AlertDescription>
                Changes are locked until the judging stage is closed. Contact a
                superadmin if something needs to be corrected.
            </AlertDescription>
        </Alert>
    );
}
