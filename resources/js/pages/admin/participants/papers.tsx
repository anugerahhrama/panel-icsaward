import { Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    FilePreviewDialog,
    type SubmittedFile,
} from '@/components/submissions/file-preview';
import {
    ParticipantsTable,
    type ParticipantTableProps,
} from '@/components/admin/participants/participants-table';
import { VerificationSheet } from '@/components/admin/participants/verification-sheet';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/participants/papers';
import { createPaperColumns, type PaperRow } from './papers-columns';

export default function PaperSubmissions(
    props: ParticipantTableProps<PaperRow>,
) {
    const [sheetOpen, setSheetOpen] = useState(false);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [previewFile, setPreviewFile] = useState<SubmittedFile | null>(null);

    // Read the row from the latest props so the sheet reflects the saved decision after a reload.
    const selected =
        props.submissions.data.find(
            (submission) => submission.id === selectedId,
        ) ?? null;

    const columns = createPaperColumns({
        onReview: (submission) => {
            setSelectedId(submission.id);
            setSheetOpen(true);
        },
        onPreview: setPreviewFile,
    });

    return (
        <>
            <Head title="Paper Submissions" />
            <ParticipantsTable
                {...props}
                tab="papers"
                description="Submissions whose paper has been uploaded. Review each one to qualify it, request a revision or disqualify it."
                columns={columns}
                defaultSort="-paper_uploaded_at"
                emptyMessage="No paper submissions match these filters."
            />
            <VerificationSheet
                submission={selected}
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                onPreview={setPreviewFile}
            />
            <FilePreviewDialog
                file={previewFile}
                onOpenChange={(open) => !open && setPreviewFile(null)}
            />
        </>
    );
}

PaperSubmissions.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Paper Submissions', href: index() },
    ],
};
