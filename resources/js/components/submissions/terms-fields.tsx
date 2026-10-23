import type {
    ApplicantType,
    AwardCategory,
} from '@/components/submissions/initiative-fields';
import { APPLICANT_TYPE_LABELS } from '@/components/submissions/initiative-fields';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

type TermsFieldsProps = {
    category: AwardCategory | undefined;
    terms: Record<ApplicantType, string>;
    accepted: boolean;
    invalid: boolean;
    onAcceptedChange: (accepted: boolean) => void;
};

export function TermsFields({
    category,
    terms,
    accepted,
    invalid,
    onAcceptedChange,
}: TermsFieldsProps) {
    const applicantType = category?.applicant_type ?? 'organization';
    const termPoints = terms[applicantType]
        .split('\n')
        .map((point) => point.trim())
        .filter((point) => point !== '');

    return (
        <div className="grid gap-4">
            <div className="grid gap-1">
                <h2 className="font-display text-base font-semibold">
                    Terms &amp; Conditions —{' '}
                    {APPLICANT_TYPE_LABELS[applicantType]}
                </h2>
                {category && (
                    <p className="text-sm text-muted-foreground">
                        {category.name}
                    </p>
                )}
            </div>

            <ol className="max-h-72 list-decimal space-y-2 overflow-y-auto rounded-lg border bg-surface p-4 pl-9 text-sm text-ink dark:bg-muted dark:text-foreground">
                {termPoints.map((point, index) => (
                    <li key={index}>{point}</li>
                ))}
            </ol>

            <div className="grid gap-2">
                <div className="flex items-start gap-3">
                    <Checkbox
                        id="terms_accepted"
                        aria-invalid={invalid}
                        checked={accepted}
                        onCheckedChange={(checked) =>
                            onAcceptedChange(checked === true)
                        }
                    />
                    <Label
                        htmlFor="terms_accepted"
                        className="leading-snug font-normal"
                    >
                        I have read and agree to the terms and conditions of ICS
                        Award 2026.
                    </Label>
                </div>
            </div>
        </div>
    );
}
