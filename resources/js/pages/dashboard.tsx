import type { ReactNode } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid,
    Tooltip as RTooltip, ResponsiveContainer, Legend,
} from 'recharts';
import {
    Building2, Users, Package, ClipboardList, HardHat, Boxes, TriangleAlert,
    ArrowRight, Check, X, Truck, PackageCheck, ScanLine, Plus, ArrowLeftRight,
    ClipboardCheck, CircleAlert,
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { IconButton } from '@/components/icon-button';
import { StatusBadge } from '@/components/status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useAuth } from '@/hooks/use-auth';
import { formatQty } from '@/lib/utils';

type Role = 'superadmin' | 'administrator' | 'engineer' | 'ics';
interface Props {
    role: Role;
    data: Record<string, unknown>;
}

/* ---------- shared bits ---------- */

function Kpi({ label, value, icon: Icon, tone = 'primary' }: {
    label: string; value: number | string; icon: typeof Building2; tone?: 'primary' | 'warning' | 'success';
}) {
    const toneClass = {
        primary: 'bg-primary/10 text-primary',
        warning: 'bg-warning/15 text-warning',
        success: 'bg-success/15 text-success',
    }[tone];
    return (
        <Card className="gap-0">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">{label}</CardTitle>
                <div className={`flex size-9 items-center justify-center rounded-lg ${toneClass}`}><Icon className="size-4.5" /></div>
            </CardHeader>
            <CardContent><p className="text-3xl font-bold tracking-tight">{value}</p></CardContent>
        </Card>
    );
}

const AXIS = { fontSize: 12, stroke: 'var(--color-muted-foreground)' };
const gridStroke = 'var(--color-border)';

function ChartCard({ title, children }: { title: string; children: ReactNode }) {
    return (
        <Card>
            <CardHeader><CardTitle className="text-base">{title}</CardTitle></CardHeader>
            <CardContent><div className="h-64 w-full">{children}</div></CardContent>
        </Card>
    );
}

function chartTooltip() {
    return (
        <RTooltip
            contentStyle={{
                background: 'var(--color-popover)',
                border: '1px solid var(--color-border)',
                borderRadius: 8,
                color: 'var(--color-popover-foreground)',
                fontSize: 12,
            }}
        />
    );
}

function InOutLine({ data }: { data: { month: string; in: number; out: number }[] }) {
    return (
        <ResponsiveContainer width="100%" height="100%">
            <LineChart data={data} margin={{ top: 8, right: 8, left: -12, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke={gridStroke} vertical={false} />
                <XAxis dataKey="month" tick={AXIS} tickLine={false} axisLine={false} />
                <YAxis tick={AXIS} tickLine={false} axisLine={false} />
                {chartTooltip()}
                <Legend wrapperStyle={{ fontSize: 12 }} />
                <Line type="monotone" dataKey="in" name="In" stroke="var(--color-chart-5)" strokeWidth={2} dot={false} />
                <Line type="monotone" dataKey="out" name="Out" stroke="var(--color-chart-1)" strokeWidth={2} dot={false} />
            </LineChart>
        </ResponsiveContainer>
    );
}

function BarSeries({ data, dataKey = 'value' }: { data: { label: string; value: number }[]; dataKey?: string }) {
    return (
        <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data} margin={{ top: 8, right: 8, left: -12, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke={gridStroke} vertical={false} />
                <XAxis dataKey="label" tick={AXIS} tickLine={false} axisLine={false} interval={0} angle={-15} textAnchor="end" height={50} />
                <YAxis tick={AXIS} tickLine={false} axisLine={false} />
                {chartTooltip()}
                <Bar dataKey={dataKey} fill="var(--color-chart-1)" radius={[4, 4, 0, 0]} />
            </BarChart>
        </ResponsiveContainer>
    );
}

function InOutBars({ data }: { data: { day: string; in: number; out: number }[] }) {
    return (
        <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data} margin={{ top: 8, right: 8, left: -12, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke={gridStroke} vertical={false} />
                <XAxis dataKey="day" tick={AXIS} tickLine={false} axisLine={false} />
                <YAxis tick={AXIS} tickLine={false} axisLine={false} />
                {chartTooltip()}
                <Legend wrapperStyle={{ fontSize: 12 }} />
                <Bar dataKey="in" name="In" fill="var(--color-chart-5)" radius={[4, 4, 0, 0]} />
                <Bar dataKey="out" name="Out" fill="var(--color-chart-1)" radius={[4, 4, 0, 0]} />
            </BarChart>
        </ResponsiveContainer>
    );
}

function LowStockList({ items }: { items: LowStock[] }) {
    return (
        <Card>
            <CardHeader><CardTitle className="flex items-center gap-2 text-base"><TriangleAlert className="size-4 text-warning" /> Low stock</CardTitle></CardHeader>
            <CardContent>
                {items.length === 0 ? <p className="py-6 text-center text-sm text-muted-foreground">All items above minimum.</p> : (
                    <ul className="flex flex-col divide-y">
                        {items.map((it) => (
                            <li key={it.id} className="flex items-center justify-between gap-3 py-2.5">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">{it.item}</p>
                                    <p className="font-mono text-xs text-muted-foreground">{it.sku} · {it.site}</p>
                                </div>
                                <Badge variant="outline" className="shrink-0 border-warning/40 bg-warning/10 text-warning">
                                    {formatQty(it.balance)} / {formatQty(it.min)}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

interface LowStock { id: number; item: string; sku: string; site: string; balance: number; min: number }

/* ---------- Admin ---------- */

interface AdminData {
    kpis: { sites: number; users: number; items: number; pending_approvals: number };
    stock_by_site: { label: string; value: number }[];
    movement_trend: { month: string; in: number; out: number }[];
    top_issued: { label: string; value: number }[];
    setup_gaps: { id: number; code: string; name: string; needs_engineer: boolean; needs_ics: boolean }[];
    low_stock_count: number;
}

function AdminDashboard({ data }: { data: AdminData }) {
    return (
        <div className="flex flex-col gap-6">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Kpi label="Sites" value={data.kpis.sites} icon={Building2} />
                <Kpi label="Users" value={data.kpis.users} icon={Users} />
                <Kpi label="Items" value={data.kpis.items} icon={Package} />
                <Kpi label="Pending approvals" value={data.kpis.pending_approvals} icon={ClipboardList} tone="warning" />
            </div>
            <div className="grid gap-6 lg:grid-cols-2">
                <ChartCard title="Stock quantity by site"><BarSeries data={data.stock_by_site} /></ChartCard>
                <ChartCard title="Movement trend — In vs Out (6 mo)"><InOutLine data={data.movement_trend} /></ChartCard>
            </div>
            <div className="grid gap-6 lg:grid-cols-2">
                <ChartCard title="Top issued items"><BarSeries data={data.top_issued} /></ChartCard>
                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><CircleAlert className="size-4 text-warning" /> Setup gaps</CardTitle></CardHeader>
                    <CardContent>
                        {data.setup_gaps.length === 0 ? <p className="py-6 text-center text-sm text-muted-foreground">Every site has an engineer and ICS.</p> : (
                            <ul className="flex flex-col divide-y">
                                {data.setup_gaps.map((s) => (
                                    <li key={s.id} className="flex items-center justify-between py-2.5">
                                        <Link href={route('sites.index')} className="text-sm font-medium hover:text-primary hover:underline">{s.name} <span className="font-mono text-xs text-muted-foreground">{s.code}</span></Link>
                                        <div className="flex gap-1">
                                            {s.needs_engineer && <Badge variant="outline" className="border-destructive/30 bg-destructive/10 text-destructive">No engineer</Badge>}
                                            {s.needs_ics && <Badge variant="outline" className="border-warning/40 bg-warning/10 text-warning">No ICS</Badge>}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

/* ---------- Engineer ---------- */

interface EngineerData {
    kpis: { awaiting_approval: number; my_sites: number; low_stock: number; in_transit: number };
    pending_queue: { id: number; ws_no: string; site: string; prepared_by: string | null; items_count: number; date: string }[];
    movement_trend: { month: string; in: number; out: number }[];
    low_stock_items: LowStock[];
}

function EngineerDashboard({ data }: { data: EngineerData }) {
    const act = (id: number, action: 'approve' | 'reject') =>
        router.post(route(`withdrawals.${action}`, id), {}, { preserveScroll: true });

    return (
        <div className="flex flex-col gap-6">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Kpi label="Awaiting my approval" value={data.kpis.awaiting_approval} icon={ClipboardList} tone="warning" />
                <Kpi label="My sites" value={data.kpis.my_sites} icon={Building2} />
                <Kpi label="Low-stock alerts" value={data.kpis.low_stock} icon={TriangleAlert} tone="warning" />
                <Kpi label="Transfers in transit" value={data.kpis.in_transit} icon={Truck} />
            </div>

            <Card>
                <CardHeader><CardTitle className="flex items-center gap-2 text-base"><ClipboardList className="size-4 text-primary" /> Approval queue</CardTitle></CardHeader>
                <CardContent>
                    {data.pending_queue.length === 0 ? <p className="py-6 text-center text-sm text-muted-foreground">Nothing awaiting approval. 🎉</p> : (
                        <ul className="flex flex-col divide-y">
                            {data.pending_queue.map((ws) => (
                                <li key={ws.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                                    <div className="min-w-0">
                                        <Link href={route('withdrawals.show', ws.id)} className="font-mono font-medium hover:text-primary hover:underline">{ws.ws_no}</Link>
                                        <p className="text-xs text-muted-foreground">{ws.site} · {ws.items_count} item(s) · by {ws.prepared_by ?? '—'}</p>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <IconButton label="Approve" className="text-success hover:text-success" onClick={() => act(ws.id, 'approve')}><Check /></IconButton>
                                        <IconButton label="Reject" className="text-destructive hover:text-destructive" onClick={() => act(ws.id, 'reject')}><X /></IconButton>
                                        <IconButton label="View" asChild><Link href={route('withdrawals.show', ws.id)}><ArrowRight /></Link></IconButton>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>

            <div className="grid gap-6 lg:grid-cols-2">
                <ChartCard title="Movement trend — In vs Out (6 mo)"><InOutLine data={data.movement_trend} /></ChartCard>
                <LowStockList items={data.low_stock_items} />
            </div>
        </div>
    );
}

/* ---------- ICS ---------- */

interface IcsData {
    kpis: { today_receipts: number; today_released: number; below_min: number; to_receive: number };
    week_flow: { day: string; in: number; out: number }[];
    to_receive: { id: number; ts_no: string; from: string; to: string; items_count: number }[];
    my_slips: { id: number; ws_no: string; site: string; status: string }[];
    low_stock_items: LowStock[];
}

function QuickAction({ href, icon: Icon, label }: { href: string; icon: typeof Plus; label: string }) {
    return (
        <Button asChild variant="outline" className="h-auto flex-col gap-2 py-4">
            <Link href={href}>
                <Icon className="size-6 text-primary" />
                <span className="text-sm font-medium">{label}</span>
            </Link>
        </Button>
    );
}

function IcsDashboard({ data }: { data: IcsData }) {
    return (
        <div className="flex flex-col gap-6">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Kpi label="Today's receipts" value={data.kpis.today_receipts} icon={Truck} tone="success" />
                <Kpi label="Released today" value={data.kpis.today_released} icon={PackageCheck} tone="success" />
                <Kpi label="Below minimum" value={data.kpis.below_min} icon={TriangleAlert} tone="warning" />
                <Kpi label="Transfers to receive" value={data.kpis.to_receive} icon={ArrowLeftRight} />
            </div>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <QuickAction href={route('receiving.create')} icon={ScanLine} label="Scan to receive" />
                <QuickAction href={route('withdrawals.create')} icon={Plus} label="New withdrawal" />
                <QuickAction href={route('transfers.create')} icon={ArrowLeftRight} label="New transfer" />
                <QuickAction href={route('inventory.count')} icon={ClipboardCheck} label="Physical count" />
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <ChartCard title="This week — In vs Out"><InOutBars data={data.week_flow} /></ChartCard>
                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><ArrowLeftRight className="size-4 text-primary" /> Transfers to receive</CardTitle></CardHeader>
                    <CardContent>
                        {data.to_receive.length === 0 ? <p className="py-6 text-center text-sm text-muted-foreground">Nothing inbound.</p> : (
                            <ul className="flex flex-col divide-y">
                                {data.to_receive.map((ts) => (
                                    <li key={ts.id} className="flex items-center justify-between py-2.5">
                                        <div>
                                            <Link href={route('transfers.show', ts.id)} className="font-mono font-medium hover:text-primary hover:underline">{ts.ts_no}</Link>
                                            <p className="text-xs text-muted-foreground">{ts.from} → {ts.to} · {ts.items_count} item(s)</p>
                                        </div>
                                        <IconButton label="Receive" asChild><Link href={route('transfers.show', ts.id)}><PackageCheck /></Link></IconButton>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><ClipboardList className="size-4 text-primary" /> My open slips</CardTitle></CardHeader>
                    <CardContent>
                        {data.my_slips.length === 0 ? <p className="py-6 text-center text-sm text-muted-foreground">No open withdrawal slips.</p> : (
                            <ul className="flex flex-col divide-y">
                                {data.my_slips.map((ws) => (
                                    <li key={ws.id} className="flex items-center justify-between py-2.5">
                                        <Link href={route('withdrawals.show', ws.id)} className="font-mono font-medium hover:text-primary hover:underline">{ws.ws_no} <span className="text-xs text-muted-foreground">{ws.site}</span></Link>
                                        <StatusBadge status={ws.status} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
                <LowStockList items={data.low_stock_items} />
            </div>
        </div>
    );
}

/* ---------- root ---------- */

export default function Dashboard({ role, data }: Props) {
    const { user } = useAuth();
    const isAdmin = role === 'administrator' || role === 'superadmin';

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-6">
                <PageHeader
                    title={`Welcome, ${user?.name.split(' ')[0] ?? ''}`}
                    description={isAdmin ? 'Company-wide inventory overview.' : role === 'engineer' ? 'Your sites and approvals.' : 'Your on-site operations.'}
                    actions={<Badge variant="secondary" className="capitalize"><HardHat className="size-3" /> {role}</Badge>}
                />
                {isAdmin && <AdminDashboard data={data as unknown as AdminData} />}
                {role === 'engineer' && <EngineerDashboard data={data as unknown as EngineerData} />}
                {role === 'ics' && <IcsDashboard data={data as unknown as IcsData} />}
            </div>
        </>
    );
}

Dashboard.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
