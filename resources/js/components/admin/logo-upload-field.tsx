import { FileIcon, UploadIcon, XIcon } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import {
    Attachment,
    AttachmentAction,
    AttachmentActions,
    AttachmentContent,
    AttachmentDescription,
    AttachmentMedia,
    AttachmentTitle,
    AttachmentTrigger,
} from '@/components/ui/attachment';
import { Label } from '@/components/ui/label';

type LogoUploadFieldProps = {
    label: string;
    currentUrl: string | null;
    file: File | null;
    onChange: (file: File | null) => void;
    error?: string;
    accept?: string;
    hint?: string;
    currentName?: string | null;
    onRemoveCurrent?: () => void;
};

export function LogoUploadField({
    label,
    currentUrl,
    file,
    onChange,
    error,
    accept = 'image/*',
    hint = 'Click to choose an image',
    currentName = null,
    onRemoveCurrent,
}: LogoUploadFieldProps) {
    const id = useId();
    const inputRef = useRef<HTMLInputElement>(null);
    const [objectUrl, setObjectUrl] = useState<string | null>(null);

    useEffect(() => {
        if (!file) {
            setObjectUrl(null);
            return;
        }

        const url = URL.createObjectURL(file);
        setObjectUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const isImageAccept = accept.startsWith('image');
    const previewUrl = isImageAccept ? (objectUrl ?? currentUrl) : null;
    const isEmpty = !file && !currentUrl;

    function clearFile() {
        onChange(null);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <input
                ref={inputRef}
                id={id}
                type="file"
                accept={accept}
                className="sr-only"
                onChange={(event) => onChange(event.target.files?.[0] ?? null)}
            />
            <Attachment
                state={error ? 'error' : isEmpty ? 'idle' : 'done'}
                className="w-full max-w-sm"
            >
                <AttachmentMedia variant={previewUrl ? 'image' : 'icon'}>
                    {previewUrl ? (
                        <img src={previewUrl} alt={`${label} preview`} />
                    ) : isEmpty ? (
                        <UploadIcon />
                    ) : (
                        <FileIcon />
                    )}
                </AttachmentMedia>
                <AttachmentContent>
                    <AttachmentTitle>
                        {file?.name ??
                            (currentUrl
                                ? (currentName ?? 'Current file')
                                : 'No file selected')}
                    </AttachmentTitle>
                    <AttachmentDescription>
                        {file ? `${(file.size / 1024).toFixed(0)} KB` : hint}
                    </AttachmentDescription>
                </AttachmentContent>
                <AttachmentTrigger
                    aria-label={`Choose ${label}`}
                    onClick={() => inputRef.current?.click()}
                />
                {file && (
                    <AttachmentActions>
                        <AttachmentAction
                            aria-label="Remove selected file"
                            onClick={clearFile}
                        >
                            <XIcon />
                        </AttachmentAction>
                    </AttachmentActions>
                )}
                {!file && currentUrl && onRemoveCurrent && (
                    <AttachmentActions>
                        <AttachmentAction
                            aria-label="Remove current file"
                            onClick={onRemoveCurrent}
                        >
                            <XIcon />
                        </AttachmentAction>
                    </AttachmentActions>
                )}
            </Attachment>
            <InputError message={error} />
        </div>
    );
}
