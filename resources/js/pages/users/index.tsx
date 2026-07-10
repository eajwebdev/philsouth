import * as React from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Users, Plus, Pencil, Trash2, Search } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { DataTable } from '@/components/data-table';
import { Pagination } from '@/components/pagination';
import { IconButton } from '@/components/icon-button';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Paginated } from '@/types';

interface UserRow {
    id: number;
    name: string;
    email: string;
    roles: { name: string }[];
    sites: { id: number; code: string; name: string }[];
}
interface Props {
    users: Paginated<UserRow>;
    filters: { search: string | null };
    roles: string[];
}

export default function UsersIndex({ users, filters, roles }: Props) {
    const [search, setSearch] = React.useState(filters.search ?? '');
    const [formOpen, setFormOpen] = React.useState(false);
    const [editing, setEditing] = React.useState<UserRow | null>(null);
    const [deleting, setDeleting] = React.useState<UserRow | null>(null);

    const onSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('users.index'), { search }, { preserveState: true, replace: true });
    };

    const columns: ColumnDef<UserRow>[] = [
        {
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }) => (
                <div>
                    <p className="font-medium">{row.original.name}</p>
                    <p className="text-xs text-muted-foreground">{row.original.email}</p>
                </div>
            ),
        },
        {
            id: 'roles',
            header: 'Role',
            enableSorting: false,
            cell: ({ row }) => (
                <div className="flex flex-wrap gap-1">
                    {row.original.roles.map((r) => (
                        <Badge key={r.name} variant="secondary" className="capitalize">{r.name}</Badge>
                    ))}
                </div>
            ),
        },
        {
            id: 'sites',
            header: 'Sites',
            enableSorting: false,
            cell: ({ row }) =>
                row.original.sites.length ? (
                    <span className="text-sm text-muted-foreground">
                        {row.original.sites.map((s) => s.code).join(', ')}
                    </span>
                ) : (
                    <span className="text-sm text-muted-foreground/60">—</span>
                ),
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            enableSorting: false,
            cell: ({ row }) => (
                <div className="flex justify-end gap-0.5">
                    <IconButton label="Edit" onClick={() => { setEditing(row.original); setFormOpen(true); }}>
                        <Pencil />
                    </IconButton>
                    <IconButton
                        label="Delete"
                        className="text-destructive hover:text-destructive"
                        onClick={() => setDeleting(row.original)}
                    >
                        <Trash2 />
                    </IconButton>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Users" />
            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Users"
                    description="Manage accounts and their global roles."
                    icon={Users}
                    actions={
                        <Button onClick={() => { setEditing(null); setFormOpen(true); }}>
                            <Plus /> New user
                        </Button>
                    }
                />

                <form onSubmit={onSearch} className="relative max-w-sm">
                    <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search users…" className="pl-9" />
                </form>

                <DataTable columns={columns} data={users.data} emptyState="No users found." />
                <Pagination meta={users} />
            </div>

            <UserFormDialog open={formOpen} onOpenChange={setFormOpen} user={editing} roles={roles} />

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(o) => !o && setDeleting(null)}
                title={`Delete ${deleting?.name}?`}
                description="This removes the user account and their site assignments."
                confirmLabel="Delete user"
                onConfirm={() => {
                    if (!deleting) return;
                    router.delete(route('users.destroy', deleting.id), {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    });
                }}
            />
        </>
    );
}

function UserFormDialog({
    open,
    onOpenChange,
    user,
    roles,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    user: UserRow | null;
    roles: string[];
}) {
    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: roles[roles.length - 1] ?? 'ics',
    });

    React.useEffect(() => {
        if (open) {
            setData({
                name: user?.name ?? '',
                email: user?.email ?? '',
                password: '',
                password_confirmation: '',
                role: user?.roles[0]?.name ?? (roles[roles.length - 1] ?? 'ics'),
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, user]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = {
            preserveScroll: true,
            onSuccess: () => { onOpenChange(false); reset(); },
        };
        if (user) put(route('users.update', user.id), opts);
        else post(route('users.store'), opts);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>{user ? 'Edit user' : 'New user'}</DialogTitle>
                        <DialogDescription>
                            {user ? 'Update the account and role.' : 'Create an account and assign a role.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} aria-invalid={!!errors.name} />
                            {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} aria-invalid={!!errors.email} />
                            {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="role">Role</Label>
                            <Select value={data.role} onValueChange={(v) => setData('role', v)}>
                                <SelectTrigger id="role">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((r) => (
                                        <SelectItem key={r} value={r} className="capitalize">{r}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.role && <p className="text-sm text-destructive">{errors.role}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {user ? 'New password' : 'Password'}
                                </Label>
                                <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} aria-invalid={!!errors.password} placeholder={user ? 'Leave blank to keep' : ''} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">Confirm</Label>
                                <Input id="password_confirmation" type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} />
                            </div>
                        </div>
                        {errors.password && <p className="-mt-2 text-sm text-destructive">{errors.password}</p>}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
                        <Button type="submit" disabled={processing}>{user ? 'Save changes' : 'Create user'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

UsersIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
