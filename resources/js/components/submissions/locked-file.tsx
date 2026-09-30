import { Download, FileText, Lock } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function LockedFile({
    label,
    name,
    href,
    detail,
}: {
    label: string;
    name: string;
    href?: string | null;
    detail?: string;
}) {
    return (
        <div className="flex items-center gap-3 rounded-lg border p-3 text-sm">
            <FileText className="size-4 shrink-0 text-muted-foreground" />
            <div className="grid min-w-0 flex-1 gap-0.5">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="truncate font-medium">{name}</p>
                {detail && (
                    <p className="text-xs text-muted-foreground">{detail}</p>
                )}
            </div>
            {href ? (
                <Button variant="ghost" size="icon" asChild>
                    <a href={href} download>
                        <Download />
                        <span className="sr-only">Download {name}</span>
                    </a>
                </Button>
            ) : (
                <Lock className="size-4 shrink-0 text-muted-foreground" />
            )}
        </div>
    );
}
