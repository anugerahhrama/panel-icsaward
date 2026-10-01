import { FileText } from 'lucide-react';
import { useState } from 'react';
import {
    FilePreviewActions,
    FilePreviewFrame,
    OFFICE_PREVIEW_NOTE,
    type SubmittedFile,
} from '@/components/submissions/file-preview';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatDateTimeWib } from '@/lib/datetime';
import { cn } from '@/lib/utils';

type Document = 'paper' | 'statement';

/**
 * The participant's paper and statement letter rendered next to the scoring form, switchable when both exist.
 */
export function DocumentPreviewPanel({
    paper,
    statement,
    paperUploadedAt,
    className,
}: {
    paper: SubmittedFile | null;
    statement: SubmittedFile | null;
    paperUploadedAt: string | null;
    className?: string;
}) {
    const [selected, setSelected] = useState<Document>(
        paper ? 'paper' : 'statement',
    );
    const file = selected === 'paper' ? paper : statement;

    return (
        <section
            aria-label="Submitted documents"
            className={cn(
                'flex flex-col gap-3 rounded-xl border bg-card p-4',
                className,
            )}
        >
            {paper && statement && (
                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    value={selected}
                    onValueChange={(value) =>
                        value && setSelected(value as Document)
                    }
                    className="self-start"
                >
                    <ToggleGroupItem value="paper" className="px-3">
                        Paper
                    </ToggleGroupItem>
                    <ToggleGroupItem value="statement" className="px-3">
                        Statement letter
                    </ToggleGroupItem>
                </ToggleGroup>
            )}

            {file ? (
                <>
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div className="grid min-w-0 gap-0.5">
                            <p className="truncate text-sm font-medium">
                                {file.name}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {selected === 'paper'
                                    ? paperUploadedAt
                                        ? `Submission paper · Uploaded ${formatDateTimeWib(paperUploadedAt)}`
                                        : 'Submission paper'
                                    : 'Statement letter'}
                            </p>
                        </div>
                        <FilePreviewActions file={file} />
                    </div>
                    {file.preview_kind === 'office' && (
                        <p className="text-xs text-muted-foreground">
                            {OFFICE_PREVIEW_NOTE}
                        </p>
                    )}
                    <FilePreviewFrame file={file} />
                </>
            ) : (
                <div className="flex min-h-0 flex-1 flex-col items-center justify-center gap-2 rounded-md border border-dashed p-4 text-center">
                    <FileText className="size-8 text-muted-foreground" />
                    <p className="text-sm text-muted-foreground">
                        No document uploaded.
                    </p>
                </div>
            )}
        </section>
    );
}
