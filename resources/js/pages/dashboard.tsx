import type { ReactNode } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    BarChart, Bar, AreaChart, Area, XAxis, YAxis, CartesianGrid,
    Tooltip as RTooltip, ResponsiveContainer,
} from 'recharts';
import {
    Building2, Users, Package, ClipboardList, HardHat, TriangleAlert,
    ArrowRight, Check, X, Truck, PackageCheck, ScanLine, Plus, ArrowLeftRight,
    ClipboardCheck, CircleAlert, Inbox, CheckCircle2,
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { IconButton } from '@/components/icon-button';
import { StatusBadge } from '@/components/status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { useAuth } from '@/hooks/use-auth';
import { formatQty } from '@/lib/utils';

type Role = 'superadmin' | 'administrator' | 'engineer' | 'ics';
interface Props {
    role: Role;
    data: Record<string, unknown>;
}

/* ---------- shared bits ---------- */

// In vs Out keep one hue pairing everywhere: green = inflow, gold = outflow.
const SERIES = { in: 'var(--color-chart-5)', out: 'var(--color-chart-1)' };
const compact = (n: number) =>
    Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(n);
const AXIS = { fontSize: 11, fill: 'var(--color-muted-foreground)' } as const;

function SectionLabel({ children }: { children: ReactNode }) {
    return <h2 className="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">{children}</h2>;
}

function Kpi({ label, value, icon: Icon, tone = 'primary', hint }: {
    label: string; value: number | string; icon: typeof Building2;
    tone?: 'primary' | 'warning' | 'success'; hint?: string;
}) {
    const t = {
        primary: { chip: 'bg-primary/10 text-primary', bar: 'bg-primary/70' },
        warning: { chip: 'bg-warning/15 text-warning', bar: 'bg-warning/70' },
        success: { chip: 'bg-success/15 text-success', bar: 'bg-success/70' },
    }[tone];
    return (
        <div className="relative overflow-hidden rounded-xl border bg-card p-5 shadow-sm">
            <span className={`absolute inset-y-3 left-0 w-1 rounded-full ${t.bar}`} />
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <p className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">{label}</p>
                    <p className="text-3xl font-semibold tracking-tight tabular-nums">{value}</p>
                    {hint && <p className="truncate text-xs text-muted-foreground">{hint}</p>}
                </div>
                <div className={`flex size-10 shrink-0 items-center justify-center rounded-lg ${t.chip}`}><Icon className="size-5" /></div>
            </div>
        </div>
    );
}

function ChartCard({ title, subtitle, legend, children }: {
    title: string; subtitle?: string; legend?: { label: string; color: string }[]; children: ReactNode;
}) {
    return (
        <div className="rounded-xl border bg-card p-5 shadow-sm">
            <div className="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h3 className="text-sm font-semibold">{title}</h3>
                    {subtitle && <p className="text-xs text-muted-foreground">{subtitle}</p>}
                </div>
                {legend && (
                    <div className="flex items-center gap-3">
                        {legend.map((l) => (
                            <span key={l.label} className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <span className="size-2 rounded-full" style={{ background: l.color }} />{l.label}
                            </span>
                        ))}
                    </div>
                )}
            </div>
            <div className="h-56 w-full">{children}</div>
        </div>
    );
}

/* eslint-disable @typescript-eslint/no-explicit-any */
function ChartTip({ active, payload, label }: any) {
    if (!active || !payload?.length) return null;
    return (
        <div className="rounded-lg border bg-popover px-3 py-2 shadow-lg">
            <p className="mb-1.5 text-xs font-medium text-popover-foreground">{label}</p>
            {payload.map((p: any) => (
                <p key={p.dataKey} className="flex items-center gap-2 text-xs text-muted-foreground">
                    <span className="size-2 rounded-full" style={{ background: p.color ?? p.fill }} />
                    <span className="capitalize">{p.name}</span>
                    <span className="ml-3 font-medium tabular-nums text-foreground">{formatQty(p.value)}</span>
                </p>
            ))}
        </div>
    );
}
/* eslint-enable @typescript-eslint/no-explicit-any */

const gridProps = { strokeDasharray: '4 4', stroke: 'var(--color-border)', vertical: false, opacity: 0.7 } as const;

function InOutTrend({ data }: { data: { month: string; in: number; out: number }[] }) {
    return (
        <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={data} margin={{ top: 6, right: 6, left: -14, bottom: 0 }}>
                <defs>
                    <linearGradient id="grad-in" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={SERIES.in} stopOpacity={0.28} />
                        <stop offset="100%" stopColor={SERIES.in} stopOpacity={0} />
                    </linearGradient>
                    <linearGradient id="grad-out" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={SERIES.out} stopOpacity={0.24} />
                        <stop offset="100%" stopColor={SERIES.out} stopOpacity={0} />
                    </linearGradient>
                </defs>
                <CartesianGrid {...gridProps} />
                <XAxis dataKey="month" tick={AXIS} tickLine={false} axisLine={false} dy={4} />
                <YAxis tick={AXIS} tickLine={false} axisLine={false} width={40} tickFormatter={compact} />
                <RTooltip content={<ChartTip />} cursor={{ stroke: 'var(--color-border)', strokeWidth: 1 }} />
                <Area type="monotone" dataKey="in" name="In" stroke={SERIES.in} strokeWidth={2} fill="url(#grad-in)" activeDot={{ r: 4 }} />
                <Area type="monotone" dataKey="out" name="Out" stroke={SERIES.out} strokeWidth={2} fill="url(#grad-out)" activeDot={{ r: 4 }} />
            </AreaChart>
        </ResponsiveContainer>
    );
}

function BarSeries({ data }: { data: { label: string; value: number }[] }) {
    return (
        <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data} margin={{ top: 6, right: 6, left: -14, bottom: 0 }}>
                <CartesianGrid {...gridProps} />
                <XAxis dataKey="label" tick={AXIS} tickLine={false} axisLine={false} interval={0} angle={-15} textAnchor="end" height={50} />
                <YAxis tick={AXIS} tickLine={false} axisLine={false} width={40} tickFormatter={compact} />
                <RTooltip content={<ChartTip />} cursor={{ fill: 'var(--color-muted)', opacity: 0.5 }} />
                <Bar dataKey="value" name="Qty" fill="var(--color-chart-1)" radius={[4, 4, 0, 0]} maxBarSize={40} />
            </BarChart>
        </ResponsiveContainer>
    );
}

function InOutBars({ data }: { data: { day: string; in: number; out: number }[] }) {
    return (
        <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data} margin={{ top: 6, right: 6, left: -14, bottom: 0 }} barGap={2}>
                <CartesianGrid {...gridProps} />
                <XAxis dataKey="day" tick={AXIS} tickLine={false} axisLine={false} dy={4} />
                <YAxis tick={AXIS} tickLine={false} axisLine={false} width={40} tickFormatter={compact} />
                <RTooltip content={<ChartTip />} cursor={{ fill: 'var(--color-muted)', opacity: 0.5 }} />
                <Bar dataKey="in" name="In" fill={SERIES.in} radius={[3, 3, 0, 0]} maxBarSize={22} />
                <Bar dataKey="out" name="Out" fill={SERIES.out} radius={[3, 3, 0, 0]} maxBarSize={22} />
            </BarChart>
        </ResponsiveContainer>
    );
}

function EmptyState({ icon: Icon = Inbox, children }: { icon?: typeof Inbox; children: ReactNode }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 py-10 text-center">
            <div className="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Icon className="size-5" />
            </div>
            <p className="text-sm text-muted-foreground">{children}</p>
        </div>
    );
}

/** Card used for list panels — shared header treatment across the dashboards. */
function PanelCard({ title, icon: Icon, tone = 'primary', children }: {
    title: string; icon: typeof Building2; tone?: 'primary' | 'warning'; children: ReactNode;
}) {
    const iconClass = tone === 'warning' ? 'text-warning' : 'text-primary';
    return (
        <Card className="gap-0 py-0">
            <CardHeader className="border-b py-4">
                <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                    <Icon className={`size-4 ${iconClass}`} /> {title}
                </CardTitle>
            </CardHeader>
            <CardContent className="p-2">{children}</CardContent>
        </Card>
    );
}

function LowStockList({ items }: { items: LowStock[] }) {
    return (
        <PanelCard title="Low stock" icon={TriangleAlert} tone="warning">
            {items.length === 0 ? <EmptyState icon={CheckCircle2}>All items above minimum.</EmptyState> : (
                <ul className="flex flex-col divide-y">
                    {items.map((it) => (
                        <li key={it.id} className="flex items-center justify-between gap-3 px-2 py-2.5">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium">{it.item}</p>
                                <p className="font-mono text-xs text-muted-foreground">{it.sku} · {it.site}</p>
                            </div>
                            <Badge variant="outline" className="shrink-0 border-warning/40 bg-warning/10 tabular-nums text-warning">
                                {formatQty(it.balance)} / {formatQty(it.min)}
                            </Badge>
                        </li>
                    ))}
                </ul>
            )}
        </PanelCard>
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
    const inOut: { label: string; color: string }[] = [
        { label: 'In', color: SERIES.in }, { label: 'Out', color: SERIES.out },
    ];
    return (
        <div className="flex flex-col gap-5">
            <SectionLabel>Overview</SectionLabel>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Kpi label="Sites" value={data.kpis.sites} icon={Building2} hint="Active project sites" />
                <Kpi label="Users" value={data.kpis.users} icon={Users} hint="Engineers, ICS & admins" />
                <Kpi label="Items" value={data.kpis.items} icon={Package} hint="Catalog items" />
                <Kpi label="Pending approvals" value={data.kpis.pending_approvals} icon={ClipboardList} tone="warning" hint="Awaiting an engineer" />
            </div>

            <SectionLabel>Activity</SectionLabel>
            <div className="grid gap-5 lg:grid-cols-2">
                <ChartCard title="Stock quantity by site" subtitle="Total on-hand per site"><BarSeries data={data.stock_by_site} /></ChartCard>
                <ChartCard title="Movement trend" subtitle="In vs Out · last 6 months" legend={inOut}><InOutTrend data={data.movement_trend} /></ChartCard>
            </div>
            <div className="grid gap-5 lg:grid-cols-2">
                <ChartCard title="Top issued items" subtitle="By quantity released"><BarSeries data={data.top_issued} /></ChartCard>
                <PanelCard title="Setup gaps" icon={CircleAlert} tone="warning">
                    {data.setup_gaps.length === 0 ? <EmptyState icon={CheckCircle2}>Every site has an engineer and ICS.</EmptyState> : (
                        <ul className="flex flex-col divide-y">
                            {data.setup_gaps.map((s) => (
                                <li key={s.id} className="flex items-center justify-between px-2 py-2.5">
                                    <Link href={route('sites.index')} className="text-sm font-medium hover:text-primary hover:underline">{s.name} <span className="font-mono text-xs text-muted-foreground">{s.code}</span></Link>
                                    <div className="flex gap-1">
                                        {s.needs_engineer && <Badge variant="outline" className="border-destructive/30 bg-destructive/10 text-destructive">No engineer</Badge>}
                                        {s.needs_ics && <Badge variant="outline" className="border-warning/40 bg-warning/10 text-warning">No ICS</Badge>}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </PanelCard>
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
        <div className="flex flex-col gap-5">
            <SectionLabel>Overview</SectionLabel>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Kpi label="Awaiting my approval" value={data.kpis.awaiting_approval} icon={ClipboardList} tone="warning" hint="Withdrawal slips" />
                <Kpi label="My sites" value={data.kpis.my_sites} icon={Building2} hint="Assigned to you" />
                <Kpi label="Low-stock alerts" value={data.kpis.low_stock} icon={TriangleAlert} tone="warning" hint="Below minimum" />
                <Kpi label="Transfers in transit" value={data.kpis.in_transit} icon={Truck} hint="Being moved" />
            </div>

            <PanelCard title="Approval queue" icon={ClipboardList}>
                {data.pending_queue.length === 0 ? <EmptyState icon={CheckCircle2}>Nothing awaiting approval.</EmptyState> : (
                    <ul className="flex flex-col divide-y">
                        {data.pending_queue.map((ws) => (
                            <li key={ws.id} className="flex flex-wrap items-center justify-between gap-3 px-2 py-3">
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
            </PanelCard>

            <SectionLabel>Activity</SectionLabel>
            <div className="grid gap-5 lg:grid-cols-2">
                <ChartCard title="Movement trend" subtitle="In vs Out · last 6 months" legend={[{ label: 'In', color: SERIES.in }, { label: 'Out', color: SERIES.out }]}><InOutTrend data={data.movement_trend} /></ChartCard>
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
        <Link
            href={href}
            className="card-lift group flex items-center gap-3 rounded-xl border bg-card p-4 shadow-sm"
        >
            <span className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                <Icon className="size-5" />
            </span>
            <span className="text-sm font-medium">{label}</span>
        </Link>
    );
}

function IcsDashboard({ data }: { data: IcsData }) {
    return (
        <div className="flex flex-col gap-5">
            <SectionLabel>Today</SectionLabel>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Kpi label="Today's receipts" value={data.kpis.today_receipts} icon={Truck} tone="success" hint="Deliveries received" />
                <Kpi label="Released today" value={data.kpis.today_released} icon={PackageCheck} tone="success" hint="Withdrawals issued" />
                <Kpi label="Below minimum" value={data.kpis.below_min} icon={TriangleAlert} tone="warning" hint="Need restocking" />
                <Kpi label="Transfers to receive" value={data.kpis.to_receive} icon={ArrowLeftRight} hint="Inbound to your site" />
            </div>

            <SectionLabel>Quick actions</SectionLabel>
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <QuickAction href={route('receiving.create')} icon={ScanLine} label="Scan to receive" />
                <QuickAction href={route('withdrawals.create')} icon={Plus} label="New withdrawal" />
                <QuickAction href={route('transfers.create')} icon={ArrowLeftRight} label="New transfer" />
                <QuickAction href={route('inventory.count')} icon={ClipboardCheck} label="Physical count" />
            </div>

            <SectionLabel>Activity</SectionLabel>
            <div className="grid gap-5 lg:grid-cols-2">
                <ChartCard title="This week" subtitle="In vs Out · daily" legend={[{ label: 'In', color: SERIES.in }, { label: 'Out', color: SERIES.out }]}><InOutBars data={data.week_flow} /></ChartCard>
                <PanelCard title="Transfers to receive" icon={ArrowLeftRight}>
                    {data.to_receive.length === 0 ? <EmptyState>Nothing inbound.</EmptyState> : (
                        <ul className="flex flex-col divide-y">
                            {data.to_receive.map((ts) => (
                                <li key={ts.id} className="flex items-center justify-between px-2 py-2.5">
                                    <div>
                                        <Link href={route('transfers.show', ts.id)} className="font-mono font-medium hover:text-primary hover:underline">{ts.ts_no}</Link>
                                        <p className="text-xs text-muted-foreground">{ts.from} → {ts.to} · {ts.items_count} item(s)</p>
                                    </div>
                                    <IconButton label="Receive" asChild><Link href={route('transfers.show', ts.id)}><PackageCheck /></Link></IconButton>
                                </li>
                            ))}
                        </ul>
                    )}
                </PanelCard>
            </div>

            <div className="grid gap-5 lg:grid-cols-2">
                <PanelCard title="My open slips" icon={ClipboardList}>
                    {data.my_slips.length === 0 ? <EmptyState>No open withdrawal slips.</EmptyState> : (
                        <ul className="flex flex-col divide-y">
                            {data.my_slips.map((ws) => (
                                <li key={ws.id} className="flex items-center justify-between px-2 py-2.5">
                                    <Link href={route('withdrawals.show', ws.id)} className="font-mono font-medium hover:text-primary hover:underline">{ws.ws_no} <span className="text-xs text-muted-foreground">{ws.site}</span></Link>
                                    <StatusBadge status={ws.status} />
                                </li>
                            ))}
                        </ul>
                    )}
                </PanelCard>
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
