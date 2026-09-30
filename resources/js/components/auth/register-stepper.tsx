import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

type RegisterStepperProps = {
    steps: readonly string[];
    currentStep: number;
};

export function RegisterStepper({ steps, currentStep }: RegisterStepperProps) {
    return (
        <ol className="flex items-center gap-2" aria-label="Registration steps">
            {steps.map((label, index) => {
                const isComplete = index < currentStep;
                const isCurrent = index === currentStep;

                return (
                    <li
                        key={label}
                        className="flex flex-1 items-center gap-2"
                        aria-current={isCurrent ? 'step' : undefined}
                    >
                        <span
                            className={cn(
                                'flex size-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold transition-colors',
                                isComplete &&
                                    'border-brand-teal bg-brand-teal text-white',
                                isCurrent && 'border-brand bg-brand text-white',
                                !isComplete &&
                                    !isCurrent &&
                                    'border-border text-muted-foreground',
                            )}
                        >
                            {isComplete ? (
                                <Check className="size-4" aria-hidden />
                            ) : (
                                index + 1
                            )}
                        </span>
                        <span
                            className={cn(
                                'text-sm font-medium',
                                isCurrent
                                    ? 'text-brand'
                                    : 'text-muted-foreground',
                                !isCurrent && 'hidden sm:inline',
                            )}
                        >
                            {label}
                        </span>
                        {index < steps.length - 1 && (
                            <span
                                className={cn(
                                    'h-px flex-1',
                                    isComplete ? 'bg-brand-teal' : 'bg-border',
                                )}
                                aria-hidden
                            />
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
