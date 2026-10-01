import { Deferred, Head } from '@inertiajs/react';
import { ApplicantTypeSplit } from '@/components/admin/overview/applicant-type-split';
import { AttentionPanel } from '@/components/admin/overview/attention-panel';
import { CategoryTable } from '@/components/admin/overview/category-table';
import { JudgeProgress } from '@/components/admin/overview/judge-progress';
import { OverviewBanner } from '@/components/admin/overview/overview-banner';
import { RecentActivity } from '@/components/admin/overview/recent-activity';
import { RegistrationTrend } from '@/components/admin/overview/registration-trend';
import { StatusCards } from '@/components/admin/overview/status-cards';
import type {
    ActivityEvent,
    ApplicantTypeCount,
    AttentionItem,
    JudgeProgress as JudgeProgressData,
    OverviewCategory,
    OverviewSummary,
    StatusCounts,
    TrendDay,
} from '@/components/admin/overview/types';
import { Skeleton } from '@/components/ui/skeleton';
import { dashboard } from '@/routes/admin';

type Props = {
    summary: OverviewSummary;
    statusCounts: StatusCounts;
    applicantTypes: ApplicantTypeCount[];
    categories: OverviewCategory[];
    attention: AttentionItem[];
    judgeProgress: JudgeProgressData;
    trend?: TrendDay[];
    recentActivity?: ActivityEvent[];
};

export default function AdminDashboard({
    summary,
    statusCounts,
    applicantTypes,
    categories,
    attention,
    judgeProgress,
    trend,
    recentActivity,
}: Props) {
    return (
        <>
            <Head title="Admin Overview" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <OverviewBanner summary={summary} statusCounts={statusCounts} />

                <StatusCards statusCounts={statusCounts} />

                <div className="grid gap-4 lg:grid-cols-2">
                    <AttentionPanel items={attention} />
                    <JudgeProgress progress={judgeProgress} />
                </div>

                <CategoryTable
                    categories={categories}
                    stageLabel={judgeProgress.stage.label}
                />

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <Deferred
                            data="trend"
                            fallback={
                                <Skeleton className="h-80 w-full rounded-xl" />
                            }
                        >
                            {trend && <RegistrationTrend days={trend} />}
                        </Deferred>
                    </div>
                    <ApplicantTypeSplit counts={applicantTypes} />
                </div>

                <Deferred
                    data="recentActivity"
                    fallback={<Skeleton className="h-64 w-full rounded-xl" />}
                >
                    {recentActivity && (
                        <RecentActivity events={recentActivity} />
                    )}
                </Deferred>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Admin Overview',
            href: dashboard(),
        },
    ],
};
