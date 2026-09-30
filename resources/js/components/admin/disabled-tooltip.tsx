import type { ReactNode } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * Explains why a disabled control is unavailable. A disabled button fires no pointer events,
 * so it is wrapped in a focusable span for the tooltip to open. Without a `reason` the
 * children render as they are.
 */
export function DisabledTooltip({
    reason,
    children,
}: {
    reason: string | null;
    children: ReactNode;
}) {
    if (reason === null) {
        return children;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span tabIndex={0} className="inline-flex">
                    {children}
                </span>
            </TooltipTrigger>
            <TooltipContent>{reason}</TooltipContent>
        </Tooltip>
    );
}
