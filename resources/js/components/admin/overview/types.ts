import type { ApplicantType } from '@/pages/admin/categories/columns';
import type { SubmissionStatus } from '@/components/submissions/submission-status-card';

export type RegistrationState = 'open' | 'not_open' | 'closed';

export type OverviewSummary = {
    registration: RegistrationState;
    judgingStage: { value: string; label: string };
    nextDeadline: { label: string; at: string } | null;
};

export type StatusCounts = {
    registrations: number;
    papers: number;
    statuses: Record<SubmissionStatus, number>;
};

export type ApplicantTypeCount = {
    type: ApplicantType;
    registrations: number;
    papers: number;
};

export type OverviewCategory = {
    id: number;
    name: string;
    applicant_type: ApplicantType;
    submissions_count: number;
    papers_count: number;
    qualified_count: number;
    finalists_count: number;
    judges_count: number;
    has_assessment_template: boolean;
    finalists_confirmed: boolean;
    awards_confirmed: boolean;
    scoring: { assigned: number; submitted: number };
};

export type AttentionItem = {
    key: string;
    count: number;
    label: string;
    href: string;
};

export type JudgeProgress = {
    stage: { value: string; label: string };
    total: { assigned: number; submitted: number; draft: number };
    judges: {
        id: number;
        name: string;
        has_account: boolean;
        assigned: number;
        submitted: number;
        draft: number;
    }[];
};

export type TrendDay = { date: string; registrations: number; papers: number };

export type ActivityEvent = {
    id: string;
    type: 'registered' | 'paper_uploaded' | 'reviewed';
    at: string;
    title: string;
    participant: string;
    category: string | null;
    status: SubmissionStatus;
    uuid: string;
    actor: string | null;
};
