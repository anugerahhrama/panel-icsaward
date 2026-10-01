/**
 * One label/value pair inside a `<dl>` in the participant sheets.
 */
export function Detail({
    label,
    value,
}: {
    label: string;
    value: React.ReactNode;
}) {
    return (
        <div className="grid gap-1">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm whitespace-pre-line">{value}</dd>
        </div>
    );
}
