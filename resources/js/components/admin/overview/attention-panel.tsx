import { Link } from '@inertiajs/react';
import { ArrowRight, CircleCheck, CircleAlert } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AttentionItem } from './types';

export function AttentionPanel({ items }: { items: AttentionItem[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Needs attention</CardTitle>
                <CardDescription>
                    Open tasks for the committee, updated on every visit.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {items.length === 0 ? (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <CircleCheck className="size-4" />
                        All caught up.
                    </p>
                ) : (
                    <ul className="-mx-2 grid gap-1">
                        {items.map((item) => (
                            <li key={item.key}>
                                <Link
                                    href={item.href}
                                    className="flex items-center gap-3 rounded-md px-2 py-2 text-sm hover:bg-muted"
                                >
                                    <CircleAlert className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                    <span className="w-8 shrink-0 font-semibold tabular-nums">
                                        {item.count}
                                    </span>
                                    <span className="flex-1">{item.label}</span>
                                    <ArrowRight className="size-4 shrink-0 text-muted-foreground" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
