import { Check, Copy } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';

export function CopyPasswordButton({ value }: { value: string }) {
    const [copiedText, copy] = useClipboard();
    const isCopied = copiedText === value && value !== '';

    return (
        <Button
            type="button"
            variant="outline"
            size="icon"
            disabled={value === ''}
            aria-label="Copy password"
            onClick={async () => {
                if (await copy(value)) {
                    toast.success('Password copied');
                } else {
                    toast.error('Could not copy the password.');
                }
            }}
        >
            {isCopied ? <Check /> : <Copy />}
        </Button>
    );
}
