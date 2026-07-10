import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export function Pagination<T>({ meta }: { meta: Paginated<T> }) {
    if (meta.last_page <= 1) return null;

    return (
        <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <p className="text-sm text-muted-foreground">
                Showing <span className="font-medium">{meta.from ?? 0}</span>–
                <span className="font-medium">{meta.to ?? 0}</span> of{' '}
                <span className="font-medium">{meta.total}</span>
            </p>
            <div className="flex flex-wrap items-center gap-1">
                {meta.links.map((link, i) => {
                    const isArrow = link.label.includes('Previous') || link.label.includes('Next');
                    const label = link.label
                        .replace('&laquo; Previous', '')
                        .replace('Next &raquo;', '');

                    if (!link.url) {
                        return (
                            <span
                                key={i}
                                className="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm text-muted-foreground/50"
                            >
                                {isArrow ? (link.label.includes('Previous') ? <ChevronLeft className="size-4" /> : <ChevronRight className="size-4" />) : <span dangerouslySetInnerHTML={{ __html: label }} />}
                            </span>
                        );
                    }

                    return (
                        <Link
                            key={i}
                            href={link.url}
                            preserveScroll
                            preserveState
                            className={cn(
                                'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm transition-colors',
                                link.active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-background hover:bg-accent',
                            )}
                        >
                            {link.label.includes('Previous') ? (
                                <ChevronLeft className="size-4" />
                            ) : link.label.includes('Next') ? (
                                <ChevronRight className="size-4" />
                            ) : (
                                <span dangerouslySetInnerHTML={{ __html: label }} />
                            )}
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
