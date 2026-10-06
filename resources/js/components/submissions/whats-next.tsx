export type TimelineStage = {
    title: string;
    description: string;
    date?: string | null;
};

export function WhatsNext({ stages }: { stages: TimelineStage[] }) {
    return (
        <div className="grid gap-3">
            <h2 className="text-sm font-semibold">What's next</h2>
            <ol className="grid gap-3">
                {stages.map((stage, index) => (
                    <li key={index} className="flex gap-3 text-sm">
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
