import * as React from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft, HardHat, Users as UsersIcon, ClipboardList, UserPlus,
    KeyRound, ShieldCheck, ShieldOff, Trash2, Plus, Search, ArrowRightLeft, MoreVertical,
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { MultiSelect } from '@/components/multi-select';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { ClientPagination, useClientPagination } from '@/components/client-pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';

interface Person { id: number; name: string; email: string }
interface SiteRef { id: number; code: string; name: string }
interface Employee {
    id: number;
    name: string;
    position: string | null;
    is_active: boolean;
    email: string | null;
    has_access: boolean;
    pages: string[];
}
interface Page { key: string; label: string }
interface Props {
    site: { id: number; code: string; name: string; address: string | null; is_active: boolean };
    engineers: Person[];
    assignedIcs: number[];
    icsUsers: Person[];
    employees: Employee[];
    allSites: SiteRef[];
    pageCatalog: Page[];
    can: { assignIcs: boolean; manageTeam: boolean; grantAccess: boolean };
}

export default function SiteTeam({ site, engineers, assignedIcs, icsUsers, employees, allSites, pageCatalog, can }: Props) {
    return (
        <>
            <Head title={`${site.name} — Team`} />
            <div className="flex flex-col gap-6">
                <div>
                    <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
                        <Link href={route('sites.index')}><ArrowLeft /> Back to sites</Link>
                    </Button>
                    <PageHeader
                        title={site.name}
                        description={`${site.code}${site.address ? ' · ' + site.address : ''}`}
                        icon={UsersIcon}
                    />
                </div>

                {can.manageTeam && (
                    <EmployeesCard site={site} employees={employees} allSites={allSites} pageCatalog={pageCatalog} canGrant={can.grantAccess} />
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <HardHat className="size-4 text-primary" /> Engineers
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {engineers.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No engineer assigned. An administrator assigns engineers to this site.
                                </p>
                            ) : (
                                <ul className="flex flex-col gap-2">
                                    {engineers.map((e) => (
                                        <li key={e.id} className="flex items-center gap-3 rounded-lg border bg-card p-3">
                                            <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <HardHat className="size-4" />
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium">{e.name}</p>
                                                <p className="text-xs text-muted-foreground">{e.email}</p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <IcsCard site={site} icsUsers={icsUsers} assignedIcs={assignedIcs} canAssign={can.assignIcs} />
                </div>
            </div>
        </>
    );
}

/* ---------- Employees roster + access ---------- */

function EmployeesCard({ site, employees, allSites, pageCatalog, canGrant }: {
    site: Props['site']; employees: Employee[]; allSites: SiteRef[]; pageCatalog: Page[]; canGrant: boolean;
}) {
    const add = useForm({ name: '', position: '' });
    const [access, setAccess] = React.useState<Employee | null>(null);
    const [removing, setRemoving] = React.useState<Employee | null>(null);
    const [moving, setMoving] = React.useState<Employee | null>(null);
    const [query, setQuery] = React.useState('');

    const submitAdd = (e: React.FormEvent) => {
        e.preventDefault();
        add.post(route('employees.store', site.id), { preserveScroll: true, onSuccess: () => add.reset() });
    };

    const filtered = React.useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return employees;
        return employees.filter((e) =>
            e.name.toLowerCase().includes(q) ||
            (e.position ?? '').toLowerCase().includes(q) ||
            (e.email ?? '').toLowerCase().includes(q));
    }, [employees, query]);

    const pager = useClientPagination(filtered, 10);
    const withAccess = employees.filter((e) => e.has_access).length;

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-3 space-y-0">
                <CardTitle className="flex items-center gap-2 text-base">
                    <UserPlus className="size-4 text-primary" /> Site employees
                    <Badge variant="secondary" className="ml-1 tabular-nums">{employees.length}</Badge>
                </CardTitle>
                {withAccess > 0 && (
                    <span className="flex items-center gap-1 text-xs text-success">
                        <ShieldCheck className="size-3.5" /> {withAccess} with login
                    </span>
                )}
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <p className="text-sm text-muted-foreground">
                    People working this site (name + position). Used to fill “Delivered to / Received by” on
                    withdrawal slips{canGrant ? ', and can be given a system login with specific page access' : ''}.
                </p>

                <form onSubmit={submitAdd} className="flex flex-wrap items-end gap-2">
                    <div className="grid flex-1 gap-1.5" style={{ minWidth: 180 }}>
                        <Label htmlFor="emp_name">Name</Label>
                        <Input id="emp_name" value={add.data.name} onChange={(e) => add.setData('name', e.target.value)} placeholder="Full name" />
                    </div>
                    <div className="grid gap-1.5" style={{ minWidth: 160 }}>
                        <Label htmlFor="emp_pos">Position</Label>
                        <Input id="emp_pos" value={add.data.position} onChange={(e) => add.setData('position', e.target.value)} placeholder="e.g. Foreman" />
                    </div>
                    <Button type="submit" disabled={add.processing || !add.data.name.trim()}><Plus /> Add</Button>
                </form>
                {add.errors.name && <p className="-mt-2 text-sm text-destructive">{add.errors.name}</p>}

                {employees.length > 0 && (
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search employees by name, position or email…" className="pl-9" />
                    </div>
                )}

                {employees.length === 0 ? (
                    <p className="rounded-lg border border-dashed py-6 text-center text-sm text-muted-foreground">No employees yet.</p>
                ) : filtered.length === 0 ? (
                    <p className="rounded-lg border border-dashed py-6 text-center text-sm text-muted-foreground">No match for “{query}”.</p>
                ) : (
                    <>
                        <ul className="flex flex-col divide-y rounded-lg border">
                            {pager.paged.map((emp) => (
                                <li key={emp.id} className="flex items-center justify-between gap-3 p-3">
                                    <div className="flex min-w-0 items-center gap-3">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground">
                                            {emp.name.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase()}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {emp.name}
                                                {emp.position && <span className="ml-2 text-xs text-muted-foreground">{emp.position}</span>}
                                            </p>
                                            {emp.has_access ? (
                                                <span className="inline-flex items-center gap-1 text-xs text-success">
                                                    <ShieldCheck className="size-3" /> {emp.email} · {emp.pages.length} page(s)
                                                </span>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">Roster only — no login</span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1">
                                        {canGrant && (
                                            <Button variant="outline" size="sm" className="hidden sm:inline-flex" onClick={() => setAccess(emp)}>
                                                <KeyRound /> {emp.has_access ? 'Access' : 'Grant access'}
                                            </Button>
                                        )}
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon-sm" aria-label="More actions"><MoreVertical /></Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                {canGrant && (
                                                    <DropdownMenuItem className="sm:hidden" onClick={() => setAccess(emp)}>
                                                        <KeyRound /> {emp.has_access ? 'Manage access' : 'Grant access'}
                                                    </DropdownMenuItem>
                                                )}
                                                <DropdownMenuItem onClick={() => setMoving(emp)}>
                                                    <ArrowRightLeft /> Move to another site
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem variant="destructive" onClick={() => setRemoving(emp)}>
                                                    <Trash2 /> Remove
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </li>
                            ))}
                        </ul>
                        <ClientPagination {...pager} />
                    </>
                )}
            </CardContent>

            {access && (
                <AccessDialog
                    key={access.id}
                    employee={access}
                    pageCatalog={pageCatalog}
                    onClose={() => setAccess(null)}
                />
            )}

            {moving && (
                <MoveDialog
                    key={moving.id}
                    employee={moving}
                    currentSiteId={site.id}
                    sites={allSites}
                    onClose={() => setMoving(null)}
                />
            )}

            <ConfirmDialog
                open={!!removing}
                onOpenChange={(o) => !o && setRemoving(null)}
                title={`Remove ${removing?.name}?`}
                description={removing?.has_access
                    ? 'This removes the employee and revokes their login.'
                    : 'This removes the employee from the site roster.'}
                confirmLabel="Remove"
                onConfirm={() => removing && router.delete(route('employees.destroy', removing.id), {
                    preserveScroll: true, onFinish: () => setRemoving(null),
                })}
            />
        </Card>
    );
}

function MoveDialog({ employee, currentSiteId, sites, onClose }: {
    employee: Employee; currentSiteId: number; sites: SiteRef[]; onClose: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({ to_site_id: '' });
    const options = sites.filter((s) => s.id !== currentSiteId);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('employees.transfer', employee.id), { preserveScroll: true, onSuccess: () => onClose() });
    };

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Move {employee.name}</DialogTitle>
                        <DialogDescription>
                            Transfer this employee to another site.
                            {employee.has_access && ' Their login will follow — access re-scopes to the new site.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2 py-4">
                        <Label>Destination site</Label>
                        <Select value={data.to_site_id} onValueChange={(v) => setData('to_site_id', v)}>
                            <SelectTrigger><SelectValue placeholder="Select site" /></SelectTrigger>
                            <SelectContent>
                                {options.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.name} ({s.code})</SelectItem>)}
                            </SelectContent>
                        </Select>
                        {errors.to_site_id && <p className="text-sm text-destructive">{errors.to_site_id}</p>}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={processing || !data.to_site_id}><ArrowRightLeft /> Move</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AccessDialog({ employee, pageCatalog, onClose }: {
    employee: Employee; pageCatalog: Page[]; onClose: () => void;
}) {
    const editing = employee.has_access;
    const { data, setData, post, put, processing, errors } = useForm({
        email: employee.email ?? '',
        password: '',
        password_confirmation: '',
        pages: employee.pages,
    });

    const toggle = (key: string) =>
        setData('pages', data.pages.includes(key) ? data.pages.filter((p) => p !== key) : [...data.pages, key]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => onClose() };
        if (editing) put(route('employees.access.update', employee.id), opts);
        else post(route('employees.access.grant', employee.id), opts);
    };

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Manage access' : 'Grant access'} — {employee.name}</DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Update which pages this login can open, or reset the password.'
                                : 'Create a login for this employee and choose the pages they can open.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email {editing && <span className="text-muted-foreground">(read-only)</span>}</Label>
                            <Input id="email" type="email" value={data.email} disabled={editing}
                                onChange={(e) => setData('email', e.target.value)} placeholder="name@philsouth.test" />
                            {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="password">{editing ? 'New password' : 'Password'}</Label>
                                <Input id="password" type="password" value={data.password}
                                    onChange={(e) => setData('password', e.target.value)} placeholder={editing ? 'Leave blank to keep' : ''} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">Confirm</Label>
                                <Input id="password_confirmation" type="password" value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)} />
                            </div>
                        </div>
                        {errors.password && <p className="-mt-2 text-sm text-destructive">{errors.password}</p>}

                        <div className="grid gap-2">
                            <Label>Page access</Label>
                            <div className="grid grid-cols-1 gap-2 rounded-lg border p-3 sm:grid-cols-2">
                                {pageCatalog.map((p) => (
                                    <label key={p.key} className="flex items-center gap-2 text-sm">
                                        <Checkbox checked={data.pages.includes(p.key)} onCheckedChange={() => toggle(p.key)} />
                                        {p.label}
                                    </label>
                                ))}
                            </div>
                        </div>
                    </div>

                    <DialogFooter className="gap-2 sm:justify-between">
                        {editing && (
                            <Button
                                type="button" variant="outline"
                                className="text-destructive hover:text-destructive"
                                onClick={() => router.delete(route('employees.access.revoke', employee.id), {
                                    preserveScroll: true, onSuccess: () => onClose(),
                                })}
                            >
                                <ShieldOff /> Revoke login
                            </Button>
                        )}
                        <div className="flex gap-2">
                            <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                            <Button type="submit" disabled={processing}>{editing ? 'Save' : 'Grant access'}</Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/* ---------- ICS assignments (unchanged behaviour) ---------- */

function IcsCard({ site, icsUsers, assignedIcs, canAssign }: {
    site: Props['site']; icsUsers: Person[]; assignedIcs: number[]; canAssign: boolean;
}) {
    const { data, setData, put, processing } = useForm<{ ics_ids: (number | string)[] }>({ ics_ids: assignedIcs });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <ClipboardList className="size-4 text-primary" /> ICS assignments
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {canAssign ? (
                    <>
                        <p className="text-sm text-muted-foreground">Assign the inventory custodians (ICS) who work this site.</p>
                        <MultiSelect
                            options={icsUsers.map((u) => ({ value: u.id, label: u.name, description: u.email }))}
                            selected={data.ics_ids}
                            onChange={(v) => setData('ics_ids', v)}
                            placeholder="Select ICS staff…"
                            emptyText="No ICS users available."
                        />
                        <div>
                            <Button onClick={() => put(route('sites.team.update', site.id), { preserveScroll: true })} disabled={processing}>
                                Save ICS assignments
                            </Button>
                        </div>
                    </>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        You don't have permission to assign ICS to this site. Only an engineer assigned here
                        (or an administrator) can manage ICS.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

SiteTeam.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
