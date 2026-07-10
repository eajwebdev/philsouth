import * as React from 'react';
import { Building2, Check, ChevronsUpDown } from 'lucide-react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useAuth } from '@/hooks/use-auth';

const STORAGE_KEY = 'active_site_id';

export function useActiveSite() {
    const { user } = useAuth();
    const sites = user?.sites ?? [];
    const [activeId, setActiveId] = React.useState<number | null>(null);

    React.useEffect(() => {
        const saved = Number(localStorage.getItem(STORAGE_KEY));
        if (saved && sites.some((s) => s.id === saved)) {
            setActiveId(saved);
        } else if (sites.length) {
            setActiveId(sites[0].id);
        }
    }, [sites.length]);

    const setActive = (id: number) => {
        setActiveId(id);
        localStorage.setItem(STORAGE_KEY, String(id));
    };

    return { sites, activeId, setActive };
}

export function SiteSwitcher() {
    const [open, setOpen] = React.useState(false);
    const { sites, activeId, setActive } = useActiveSite();

    if (!sites.length) return null;

    const active = sites.find((s) => s.id === activeId) ?? sites[0];

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button variant="outline" size="sm" className="max-w-[220px] justify-between gap-2">
                    <Building2 className="size-4 shrink-0 text-primary" />
                    <span className="truncate">{active?.name ?? 'Select site'}</span>
                    <ChevronsUpDown className="size-3.5 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-64 p-0" align="start">
                <Command>
                    <CommandInput placeholder="Search sites…" />
                    <CommandList>
                        <CommandEmpty>No sites.</CommandEmpty>
                        <CommandGroup>
                            {sites.map((s) => (
                                <CommandItem
                                    key={s.id}
                                    value={s.name}
                                    onSelect={() => {
                                        setActive(s.id);
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 size-4',
                                            s.id === activeId ? 'opacity-100' : 'opacity-0',
                                        )}
                                    />
                                    <span className="flex flex-col">
                                        <span className="font-medium">{s.name}</span>
                                        <span className="text-xs text-muted-foreground">{s.code}</span>
                                    </span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
