import { Medal } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

export type Award = 'gold' | 'silver' | 'bronze';

export const AWARDS: Award[] = ['gold', 'silver', 'bronze'];

/**
 * Matches `App\Enums\Award::label()`.
 */
export const AWARD_LABELS: Record<Award, string> = {
    gold: 'Gold',
    silver: 'Silver',
    bronze: 'Bronze',
};

export function AwardBadge({ award }: { award: Award }) {
    return (
        <Badge variant={award === 'gold' ? 'default' : 'outline'}>
            <Medal />
            {AWARD_LABELS[award]}
        </Badge>
    );
}
