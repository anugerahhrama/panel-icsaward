import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

type SettingsSectionProps = {
    title: string;
    description?: string;
    defaultOpen?: boolean;
    children: React.ReactNode;
};

export function SettingsSection({
    title,
    description,
    defaultOpen = false,
    children,
}: SettingsSectionProps) {
    const [isOpen, setIsOpen] = useState(defaultOpen);

    return (
        <Card>
            <Collapsible open={isOpen} onOpenChange={setIsOpen}>
                <CollapsibleTrigger className="w-full text-left">
                    <CardHeader className="flex-row items-center justify-between">
                        <div className="space-y-1.5">
                            <h3 className="leading-none font-semibold">
                                {title}
                            </h3>
                            {description && (
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </div>
                        <ChevronDown
                            className={`size-4 shrink-0 text-muted-foreground transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
                        />
                    </CardHeader>
                </CollapsibleTrigger>
                {/* Stay mounted while collapsed so uncontrolled <Form> fields are still submitted. */}
                <CollapsibleContent
                    forceMount
                    className="data-[state=closed]:hidden"
                >
                    <CardContent className="space-y-4 pt-4">
                        {children}
                    </CardContent>
                </CollapsibleContent>
            </Collapsible>
        </Card>
    );
}
