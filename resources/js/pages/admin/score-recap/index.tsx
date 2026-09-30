import { Form, Head, router } from '@inertiajs/react';
import {
    Calculator,
    FileSpreadsheet,
    Info,
    LockKeyhole,
    Medal,
    Trophy,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import AwardController from '@/actions/App/Http/Controllers/Admin/AwardController';
import FinalistController from '@/actions/App/Http/Controllers/Admin/FinalistController';
import ScoreRecapController from '@/actions/App/Http/Controllers/Admin/ScoreRecapController';
import { DisabledTooltip } from '@/components/admin/disabled-tooltip';
import {
    type AwardCandidate,
    type AwardQuota,
    AwardsSheet,
} from '@/components/admin/score-recap/awards-sheet';
import {
    type FinalistCandidate,
    FinalistsSheet,
} from '@/components/admin/score-recap/finalists-sheet';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    type Paginated,
    type ServerTableFilters,
    useServerTable,
} from '@/hooks/use-server-table';
import { formatDateTimeWib } from '@/lib/datetime';
import { dashboard } from '@/routes/admin';
import scoreRecap from '@/routes/admin/score-recap';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    createScoreRecapColumns,
    type ScoreRecapRow,
    type ScoreRecapStage,
} from './columns';

type Category = {
    id: number;
    name: string;
    finalists_confirmed_at: string | null;
    finalists_count: number;
    awards_confirmed_at: string | null;
};

type Props = {
    submissions: Paginated<ScoreRecapRow>;
    filters: ServerTableFilters & {
        stage: ScoreRecapStage;
        category: number | null;
    };
    categories: Category[];
    calculatedAt: string | null;
    normalization: { enabled: boolean; min_sample: number };
    candidates: FinalistCandidate[] | null;
    awardCandidates: AwardCandidate[] | null;
    awardQuota: AwardQuota;
    maxFinalists: number;
    canReopenFinalists: boolean;
    weights: { stage_1: number; stage_2: number };
};

const STAGE_DESCRIPTIONS: Record<ScoreRecapStage, string> = {
    desk_evaluation:
        "Stage 1 · Desk Evaluation. Ranked per category from the judges' submitted scores; recused judges are left out.",
    pitching:
        "Stage 2 · Pitching. Finalists ranked per category from the judges' submitted pitching scores; recused judges are left out.",
    final: 'Final. Finalists ranked per category by the weighted Stage 1 and Stage 2 scores; the committee confirms the awards.',
};

const EMPTY_MESSAGES: Record<ScoreRecapStage, string> = {
    desk_evaluation:
        'No qualified submissions or finalists match these filters.',
    pitching: 'No confirmed finalists match these filters.',
    final: 'No confirmed finalists match these filters.',
};

const ALL = 'all';

export default function ScoreRecap({
    submissions,
    filters,
    categories,
    calculatedAt,
    normalization,
    candidates,
    awardCandidates,
    awardQuota,
    maxFinalists,
    canReopenFinalists,
    weights,
}: Props) {
    const [selectingFinalists, setSelectingFinalists] = useState(false);
    const [selectingAwards, setSelectingAwards] = useState(false);
    const [reopening, setReopening] = useState(false);
    const [reopenProcessing, setReopenProcessing] = useState(false);

    const stage = filters.stage;
    const isStageOne = stage === 'desk_evaluation';
    const isFinal = stage === 'final';

    const selectedCategory =
        categories.find((category) => category.id === filters.category) ?? null;
    const finalistsConfirmedCount = categories.filter(
        (category) => category.finalists_confirmed_at !== null,
    ).length;
    const awardsConfirmedCount = categories.filter(
        (category) => category.awards_confirmed_at !== null,
    ).length;
    const recalculateLockedReason = isStageOne
        ? finalistsConfirmedCount > 0
            ? `Finalists are confirmed for ${finalistsConfirmedCount} ${finalistsConfirmedCount === 1 ? 'category' : 'categories'}. Reopen them before recalculating.`
            : null
        : awardsConfirmedCount > 0
          ? `Awards are confirmed for ${awardsConfirmedCount} ${awardsConfirmedCount === 1 ? 'category' : 'categories'}. Reopen them before recalculating.`
          : null;

    const reopen = () => {
        if (!selectedCategory) {
            return;
        }

        setReopenProcessing(true);

        router.delete(
            (isFinal ? AwardController : FinalistController).destroy(
                selectedCategory.id,
            ).url,
            {
                preserveScroll: true,
                onSuccess: () => setReopening(false),
                onError: () =>
                    toast.error(
                        isFinal
                            ? 'Failed to reopen the awards.'
                            : 'Failed to reopen the finalists.',
                    ),
                onFinish: () => setReopenProcessing(false),
            },
        );
    };

    const { table, setFilter } = useServerTable({
        url: scoreRecap.index().url,
        prop: 'submissions',
        columns: createScoreRecapColumns({ stage }),
        paginator: submissions,
        filters,
        defaultSort: 'rank',
    });

    const exportQuery = Object.fromEntries(
        Object.entries({
            stage: isStageOne ? null : stage,
            search: filters.search,
            category: filters.category,
            sort: filters.sort,
        }).filter(([, value]) => value !== null && value !== ''),
    );

    return (
        <>
            <Head title="Score Recap" />
            <table.AppTable>
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <Heading
                            title="Score Recap"
                            description={STAGE_DESCRIPTIONS[stage]}
                        />
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        scoreRecap.export({
                                            query: exportQuery,
                                        }).url
                                    }
                                >
                                    <FileSpreadsheet />
                                    Export Excel
                                </a>
                            </Button>
                            <Form
                                {...(isStageOne
                                    ? ScoreRecapController.recalculate.form()
                                    : ScoreRecapController.recalculateStageTwo.form())}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <DisabledTooltip
                                        reason={recalculateLockedReason}
                                    >
                                        <Button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                recalculateLockedReason !== null
                                            }
                                        >
                                            {processing ? (
                                                <Spinner />
                                            ) : (
                                                <Calculator />
                                            )}
                                            Recalculate
                                        </Button>
                                    </DisabledTooltip>
                                )}
                            </Form>
                        </div>
                    </div>

                    <ToggleGroup
                        type="single"
                        variant="outline"
                        className="w-fit"
                        value={filters.stage}
                        onValueChange={(value) =>
                            value &&
                            router.get(
                                scoreRecap.index({
                                    query: {
                                        stage:
                                            value === 'desk_evaluation'
                                                ? undefined
                                                : value,
                                        category: filters.category ?? undefined,
                                    },
                                }).url,
                            )
                        }
                    >
                        <ToggleGroupItem
                            value="desk_evaluation"
                            className="px-4"
                        >
                            Stage 1 · Desk Evaluation
                        </ToggleGroupItem>
                        <ToggleGroupItem value="pitching" className="px-4">
                            Stage 2 · Pitching
                        </ToggleGroupItem>
                        <ToggleGroupItem value="final" className="px-4">
                            Final
                        </ToggleGroupItem>
                    </ToggleGroup>

                    <Alert>
                        <Info />
                        <AlertDescription>
                            <p>
                                {!isStageOne &&
                                    'Only the finalists of categories with confirmed finalists are scored in pitching. '}
                                {isFinal &&
                                    `Final score = Stage 1 × ${weights.stage_1}% + Stage 2 × ${weights.stage_2}% (both normalized), recalculated together with Stage 2. `}
                                {calculatedAt === null
                                    ? 'Scores have not been calculated yet. Press Recalculate once judges have submitted their scores.'
                                    : `Last calculated ${formatDateTimeWib(calculatedAt)}. Scores submitted after that appear on the next recalculation.`}
                            </p>
                            <p>
                                {normalization.enabled
                                    ? `Normalized = each judge's z-score scaled back to the overall 0–100 range. Judges with fewer than ${normalization.min_sample} submitted scores keep their weighted raw score.`
                                    : 'Normalization is off, so the normalized score equals the average weighted raw score.'}
                            </p>
                        </AlertDescription>
                    </Alert>

                    {isFinal && (
                        <AwardsBar
                            category={selectedCategory}
                            canReopen={canReopenFinalists}
                            onSelect={() => setSelectingAwards(true)}
                            onReopen={() => setReopening(true)}
                        />
                    )}

                    {isStageOne && (
                        <FinalistsBar
                            category={selectedCategory}
                            maxFinalists={maxFinalists}
                            canReopen={canReopenFinalists}
                            onSelect={() => setSelectingFinalists(true)}
                            onReopen={() => setReopening(true)}
                        />
                    )}

                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-1 flex-wrap items-center gap-2">
                            <table.Search placeholder="Search title, name, email..." />
                            <Select
                                value={filters.category?.toString() ?? ALL}
                                onValueChange={(value) =>
                                    setFilter(
                                        'category',
                                        value === ALL ? null : Number(value),
                                    )
                                }
                            >
                                <SelectTrigger size="sm" className="w-56">
                                    <SelectValue placeholder="Category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        All categories
                                    </SelectItem>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category.id}
                                            value={category.id.toString()}
                                        >
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <table.ViewOptions />
                    </div>

                    <div className="overflow-hidden rounded-md border">
                        <Table>
                            <TableHeader>
                                {table.getHeaderGroups().map((headerGroup) => (
                                    <TableRow key={headerGroup.id}>
                                        {headerGroup.headers
                                            .filter((header) =>
                                                header.column.getIsVisible(),
                                            )
                                            .map((header) => (
                                                <TableHead key={header.id}>
                                                    {header.isPlaceholder ? null : (
                                                        <table.AppHeader
                                                            header={header}
                                                        >
                                                            {(h) => (
                                                                <h.FlexRender />
                                                            )}
                                                        </table.AppHeader>
                                                    )}
                                                </TableHead>
                                            ))}
                                    </TableRow>
                                ))}
                            </TableHeader>
                            <TableBody>
                                {table.getRowModel().rows.length ? (
                                    table.getRowModel().rows.map((row) => (
                                        <TableRow key={row.id}>
                                            {row
                                                .getVisibleCells()
                                                .map((cell) => (
                                                    <TableCell key={cell.id}>
                                                        <table.AppCell
                                                            cell={cell}
                                                        >
                                                            {(c) => (
                                                                <c.FlexRender />
                                                            )}
                                                        </table.AppCell>
                                                    </TableCell>
                                                ))}
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={
                                                table.getVisibleLeafColumns()
                                                    .length
                                            }
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            {EMPTY_MESSAGES[stage]}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    <table.Pagination pageSizeOptions={[10, 20, 50, 100]} />
                </div>
            </table.AppTable>

            {selectedCategory && candidates && (
                <FinalistsSheet
                    category={selectedCategory}
                    candidates={candidates}
                    max={maxFinalists}
                    open={selectingFinalists}
                    onOpenChange={setSelectingFinalists}
                />
            )}

            {selectedCategory && awardCandidates && (
                <AwardsSheet
                    category={selectedCategory}
                    candidates={awardCandidates}
                    quota={awardQuota}
                    open={selectingAwards}
                    onOpenChange={setSelectingAwards}
                />
            )}

            <Dialog open={reopening} onOpenChange={setReopening}>
                <DialogContent>
                    <DialogTitle>
                        {isFinal ? 'Reopen awards?' : 'Reopen finalists?'}
                    </DialogTitle>
                    {isFinal ? (
                        <DialogDescription>
                            The awards of{' '}
                            <strong>{selectedCategory?.name}</strong> are
                            cleared and judges can change its pitching scores
                            again while pitching is open. Stage 2 can be
                            recalculated again once no category has confirmed
                            awards.
                        </DialogDescription>
                    ) : (
                        <DialogDescription>
                            The finalists of{' '}
                            <strong>{selectedCategory?.name}</strong> go back to
                            Qualified and disappear from their dashboards.
                            Scores can be recalculated again once no category
                            has confirmed finalists.
                        </DialogDescription>
                    )}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={reopenProcessing}
                            onClick={reopen}
                        >
                            {reopenProcessing && <Spinner />}
                            Reopen
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function FinalistsBar({
    category,
    maxFinalists,
    canReopen,
    onSelect,
    onReopen,
}: {
    category: Category | null;
    maxFinalists: number;
    canReopen: boolean;
    onSelect: () => void;
    onReopen: () => void;
}) {
    if (category === null) {
        return (
            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <Trophy className="size-4" />
                Choose a category to select its finalists (up to {maxFinalists}
                ).
            </p>
        );
    }

    if (category.finalists_confirmed_at === null) {
        return (
            <div className="flex flex-col gap-2 rounded-md border p-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm">
                    Finalists for <strong>{category.name}</strong> are not
                    confirmed yet.
                </p>
                <Button size="sm" onClick={onSelect}>
                    <Trophy />
                    Select finalists
                </Button>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-2 rounded-md border p-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-2 text-sm">
                <Badge>
                    <LockKeyhole />
                    Finalists confirmed
                </Badge>
                <span>
                    {category.finalists_count}{' '}
                    {category.finalists_count === 1 ? 'finalist' : 'finalists'}{' '}
                    · {formatDateTimeWib(category.finalists_confirmed_at)}
                </span>
            </div>
            {canReopen && (
                <Button size="sm" variant="outline" onClick={onReopen}>
                    Reopen
                </Button>
            )}
        </div>
    );
}

function AwardsBar({
    category,
    canReopen,
    onSelect,
    onReopen,
}: {
    category: Category | null;
    canReopen: boolean;
    onSelect: () => void;
    onReopen: () => void;
}) {
    if (category === null) {
        return (
            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <Medal className="size-4" />
                Choose a category to confirm its awards.
            </p>
        );
    }

    if (category.finalists_confirmed_at === null) {
        return (
            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <Medal className="size-4" />
                Finalists for {category.name} are not confirmed yet.
            </p>
        );
    }

    if (category.awards_confirmed_at === null) {
        return (
            <div className="flex flex-col gap-2 rounded-md border p-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm">
                    Awards for <strong>{category.name}</strong> are not
                    confirmed yet.
                </p>
                <Button size="sm" onClick={onSelect}>
                    <Medal />
                    Confirm awards
                </Button>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-2 rounded-md border p-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-2 text-sm">
                <Badge>
                    <LockKeyhole />
                    Awards confirmed
                </Badge>
                <span>{formatDateTimeWib(category.awards_confirmed_at)}</span>
            </div>
            {canReopen && (
                <Button size="sm" variant="outline" onClick={onReopen}>
                    Reopen
                </Button>
            )}
        </div>
    );
}

ScoreRecap.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Score Recap', href: scoreRecap.index() },
    ],
};
