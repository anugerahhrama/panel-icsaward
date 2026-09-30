import {
    STATUS_LABELS,
    type SubmissionStatus,
} from '@/components/submissions/submission-status-card';
import { Badge } from '@/components/ui/badge';

const STATUS_VARIANTS: Record<
    SubmissionStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    registered: 'outline',
    under_review: 'secondary',
    needs_revision: 'secondary',
    qualified: 'default',
    disqualified: 'destructive',
    finalist: 'default',
};

export function SubmissionStatusBadge({
    status,
}: {
    status: SubmissionStatus;
}) {
    return (
        <Badge variant={STATUS_VARIANTS[status]}>{STATUS_LABELS[status]}</Badge>
    );
}
