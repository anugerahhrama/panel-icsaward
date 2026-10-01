import { Download, ExternalLink, Eye, FileX } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';

export type SubmittedFile = {
    name: string;
    url: string;
    preview_url: string;
    preview_kind: 'pdf' | 'image' | 'office' | 'download';
};

export const OFFICE_PREVIEW_NOTE =
    "Office files are rendered by Microsoft Office Online. If the preview doesn't load, download the file.";

/**
 * File name that opens the preview dialog instead of downloading the file.
 */
export function FilePreviewButton({
    file,
    onPreview,
}: {
    file: SubmittedFile | null;
    onPreview: (file: SubmittedFile) => void;
}) {
    if (!file) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <button
            type="button"
            onClick={() => onPreview(file)}
            className="inline-flex max-w-48 items-center gap-1.5 text-left text-primary hover:underline"
            title={`Preview ${file.name}`}
        >
            <Eye className="size-3.5 shrink-0" />
            <span className="truncate">{file.name}</span>
        </button>
    );
}

/**
 * "Open in new tab" (when the file can be previewed) and "Download" buttons for a submitted file.
 */
export function FilePreviewActions({ file }: { file: SubmittedFile }) {
    return (
        <div className="flex flex-wrap gap-2">
            {file.preview_kind !== 'download' && (
                <Button variant="outline" size="sm" asChild>
                    <a href={file.preview_url} target="_blank" rel="noreferrer">
                        <ExternalLink />
                        Open in new tab
                    </a>
                </Button>
            )}
            <Button variant="outline" size="sm" asChild>
                <a href={file.url}>
                    <Download />
                    Download
                </a>
            </Button>
        </div>
    );
}

function PreviewFrame({ file }: { file: SubmittedFile }) {
    const [isLoaded, setIsLoaded] = useState(false);

    return (
        <div className="relative min-h-0 flex-1 overflow-hidden rounded-md border bg-muted">
            {!isLoaded && (
                <Skeleton className="absolute inset-0 rounded-none" />
            )}
            {file.preview_kind === 'image' ? (
                <img
                    src={file.preview_url}
                    alt={file.name}
                    onLoad={() => setIsLoaded(true)}
                    className="size-full object-contain"
                />
            ) : (
                <iframe
                    src={file.preview_url}
                    title={file.name}
                    onLoad={() => setIsLoaded(true)}
                    className="size-full bg-background"
                />
            )}
        </div>
    );
}

/**
 * The file rendered inline (PDF, image or Office Online), or a download hint when it can't be previewed.
 * Fills the remaining height of a flex column parent.
 */
export function FilePreviewFrame({ file }: { file: SubmittedFile }) {
    if (file.preview_kind === 'download') {
        return (
            <div className="flex min-h-0 flex-1 flex-col items-center justify-center gap-2 rounded-md border border-dashed p-4 text-center">
                <FileX className="size-8 text-muted-foreground" />
                <p className="text-sm text-muted-foreground">
                    Preview isn't available for this file type. Download it to
                    open it.
                </p>
            </div>
        );
    }

    return <PreviewFrame key={file.preview_url} file={file} />;
}

export function FilePreviewDialog({
    file,
    onOpenChange,
}: {
    file: SubmittedFile | null;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={file !== null} onOpenChange={onOpenChange}>
            <DialogContent className="flex h-[85vh] flex-col sm:max-w-5xl">
                {file && (
                    <>
                        <DialogHeader className="pr-8">
                            <DialogTitle className="truncate leading-normal">
                                {file.name}
                            </DialogTitle>
                            <DialogDescription>
                                {file.preview_kind === 'office'
                                    ? OFFICE_PREVIEW_NOTE
                                    : 'Preview of the file submitted by the participant.'}
                            </DialogDescription>
                            <FilePreviewActions file={file} />
                        </DialogHeader>
                        <FilePreviewFrame file={file} />
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
