export type TimelineStage = {
    title: string;
    description: string;
    date?: string | null;
};

const DEFAULT_STAGES: TimelineStage[] = [
    {
        title: 'Administrative selection',
        description:
            'The committee checks your documents for completeness. Your status becomes Qualified or Needs Revision.',
    },
    {
        title: 'Desk evaluation',
        description:
            'Qualified submissions are scored by the Board of Judges in your category.',
    },
    {
        title: 'Top 5 finalists announced',
        description:
            'Finalists are invited to a pitching session with the judges.',
    },
    {
        title: 'Awarding Night',
        description: 'Winners are announced at the Awarding Night.',
    },
];

export function WhatsNext({
    stages = DEFAULT_STAGES,
}: {
    stages?: TimelineStage[];
}) {
    return (
        <div className="grid gap-3">
            <h2 className="text-sm font-semibold">What's next</h2>
            <ol className="grid gap-3">
                {stages.map((stage, index) => (
                    <li key={stage.title} className="flex gap-3 text-sm">
                        <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand/10 text-xs font-semibold text-brand">
                            {index + 1}
                        </span>
                        <div className="grid gap-0.5">
                            <p className="font-medium">{stage.title}</p>
                            {stage.date && (
                                <p className="text-xs font-medium text-brand">
                                    {stage.date}
                                </p>
                            )}
                            <p className="text-muted-foreground">
                                {stage.description}
                            </p>
                        </div>
                    </li>
                ))}
            </ol>
        </div>
    );
}
