import { Check, Copy } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';

export function CopyPasswordButton({
    value,
    label = 'password',
}: {
    value: string;
    label?: string;
}) {
    const [copiedText, copy] = useClipboard();
    const isCopied = copiedText === value && value !== '';

    return (
        <Button
            type="button"
            variant="outline"
            size="icon"
            disabled={value === ''}
            aria-label={`Copy ${label}`}
            onClick={async () => {
                if (await copy(value)) {
                    toast.success(
                        `${label.charAt(0).toUpperCase()}${label.slice(1)} copied`,
                    );
                } else {
                    toast.error(`Could not copy the ${label}.`);
                }
            }}
        >
            {isCopied ? <Check /> : <Copy />}
        </Button>
    );
}
