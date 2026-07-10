import type { ReactNode } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Building2, Users, HardHat, ClipboardList, ArrowRight } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAuth } from '@/hooks/use-auth';
import type { SiteRef } from '@/types';

interface Props {
    kpis: { sites: number; users?: number; engineers?: number; ics?: number };
    mySites: SiteRef[];
}

function Kpi({ label, value, icon: Icon }: { label: string; value: number | string; icon: typeof Building2 }) {
    return (
        <Card className="gap-0">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">{label}</CardTitle>
                <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icon className="size-4.5" />
                </div>
            </CardHeader>
            <CardContent>
                <p className="text-3xl font-bold tracking-tight">{value}</p>
            </CardContent>
        </Card>
    );
}

export default function Dashboard({ kpis, mySites }: Props) {
    const { user } = useAuth();

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-6">
                <PageHeader
                    title={`Welcome, ${user?.name.split(' ')[0] ?? ''}`}
                    description="Here's an overview of your inventory operations."
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Kpi label="My Sites" value={kpis.sites} icon={Building2} />
                    {kpis.users !== undefined && <Kpi label="Users" value={kpis.users} icon={Users} />}
                    {kpis.engineers !== undefined && <Kpi label="Engineers" value={kpis.engineers} icon={HardHat} />}
                    {kpis.ics !== undefined && <Kpi label="ICS Staff" value={kpis.ics} icon={ClipboardList} />}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>My Sites</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {mySites.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                You have no assigned sites yet.
                            </p>
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {mySites.map((s) => (
                                    <Link
                                        key={s.id}
                                        href={route('sites.index')}
                                        className="group flex items-center justify-between rounded-xl border bg-card p-4 transition-colors hover:border-primary/40 hover:bg-accent/40"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <Building2 className="size-5" />
                                            </div>
                                            <div>
                                                <p className="font-medium">{s.name}</p>
                                                <p className="text-xs text-muted-foreground">{s.code}</p>
                                            </div>
                                        </div>
                                        <ArrowRight className="size-4 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                                    </Link>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
