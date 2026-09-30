import { Badge } from '@/components/ui/badge';

export type ScoringStatus = 'not_started' | 'draft' | 'submitted';

export const SCORING_STATUS_LABELS: Record<ScoringStatus, string> = {
    not_started: 'Not started',
    draft: 'Draft',
    submitted: 'Submitted',
};

const SCORING_STATUS_VARIANTS: Record<
    ScoringStatus,
    'default' | 'secondary' | 'outline'
> = {
    not_started: 'outline',
    draft: 'secondary',
    submitted: 'default',
};

export function ScoringStatusBadge({ status }: { status: ScoringStatus }) {
    return (
        <Badge variant={SCORING_STATUS_VARIANTS[status]}>
            {SCORING_STATUS_LABELS[status]}
        </Badge>
    );
}
