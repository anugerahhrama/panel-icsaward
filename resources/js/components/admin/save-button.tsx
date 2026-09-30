import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type SaveButtonProps = {
    processing: boolean;
    disabled?: boolean;
};

export function SaveButton({ processing, disabled = false }: SaveButtonProps) {
    return (
        <div className="flex justify-end">
            <Button type="submit" disabled={processing || disabled}>
                {processing && <Spinner />}
                Save changes
            </Button>
        </div>
    );
}
