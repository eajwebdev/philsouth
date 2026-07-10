import * as React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';

/**
 * Client-side pagination for in-memory tables (report ledgers, variant lists).
 * `showAll` bypasses paging — used while printing so the full table prints.
 */
export function useClientPagination<T>(rows: T[], perPage = 15, showAll = false) {
    const [page, setPage] = React.useState(1);
    const lastPage = Math.max(1, Math.ceil(rows.length / perPage));
    const safePage = Math.min(page, lastPage);

    React.useEffect(() => {
        setPage(1);
    }, [rows.length]);

    const paged = React.useMemo(
        () => (showAll ? rows : rows.slice((safePage - 1) * perPage, safePage * perPage)),
        [rows, safePage, perPage, showAll],
    );

    return {
        paged,
        page: safePage,
        lastPage,
        setPage,
        from: rows.length ? (safePage - 1) * perPage + 1 : 0,
        to: Math.min(safePage * perPage, rows.length),
        total: rows.length,
    };
}

export function ClientPagination({
    page,
    lastPage,
    setPage,
    from,
    to,
    total,
}: {
    page: number;
    lastPage: number;
    setPage: (p: number) => void;
    from: number;
    to: number;
    total: number;
}) {
    if (lastPage <= 1) return null;

    return (
        <div className="no-print flex flex-col items-center justify-between gap-3 pt-3 sm:flex-row">
            <p className="text-sm text-muted-foreground">
                Showing <span className="font-medium">{from}</span>–<span className="font-medium">{to}</span> of{' '}
                <span className="font-medium">{total}</span>
            </p>
            <div className="flex items-center gap-1.5">
                <Button
                    variant="outline"
                    size="icon-sm"
                    disabled={page <= 1}
                    onClick={() => setPage(page - 1)}
                    aria-label="Previous page"
                >
                    <ChevronLeft />
                </Button>
                <span className="min-w-20 text-center text-sm text-muted-foreground">
                    Page {page} of {lastPage}
                </span>
                <Button
                    variant="outline"
                    size="icon-sm"
                    disabled={page >= lastPage}
                    onClick={() => setPage(page + 1)}
                    aria-label="Next page"
                >
                    <ChevronRight />
                </Button>
            </div>
        </div>
    );
}
