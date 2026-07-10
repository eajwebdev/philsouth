import type { ReactNode } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, HardHat, Users as UsersIcon, ClipboardList } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/page-header';
import { MultiSelect } from '@/components/multi-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Person {
    id: number;
    name: string;
    email: string;
}
interface Props {
    site: { id: number; code: string; name: string; address: string | null; is_active: boolean };
    engineers: Person[];
    assignedIcs: number[];
    icsUsers: Person[];
    can: { assignIcs: boolean };
}

export default function SiteTeam({ site, engineers, assignedIcs, icsUsers, can }: Props) {
    const { data, setData, put, processing } = useForm<{ ics_ids: (number | string)[] }>({
        ics_ids: assignedIcs,
    });

    const submit = () => {
        put(route('sites.team.update', site.id), { preserveScroll: true });
    };

    return (
        <>
            <Head title={`${site.name} — Team`} />
            <div className="flex flex-col gap-6">
                <div>
                    <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
                        <Link href={route('sites.index')}>
                            <ArrowLeft /> Back to sites
                        </Link>
                    </Button>
                    <PageHeader
                        title={site.name}
                        description={`${site.code}${site.address ? ' · ' + site.address : ''}`}
                        icon={UsersIcon}
                    />
                </div>

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

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <ClipboardList className="size-4 text-primary" /> ICS assignments
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {can.assignIcs ? (
                                <>
                                    <p className="text-sm text-muted-foreground">
                                        Assign the inventory custodians (ICS) who work this site.
                                    </p>
                                    <MultiSelect
                                        options={icsUsers.map((u) => ({ value: u.id, label: u.name, description: u.email }))}
                                        selected={data.ics_ids}
                                        onChange={(v) => setData('ics_ids', v)}
                                        placeholder="Select ICS staff…"
                                        emptyText="No ICS users available."
                                    />
                                    <div>
                                        <Button onClick={submit} disabled={processing}>
                                            Save ICS assignments
                                        </Button>
                                    </div>
                                </>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    You don't have permission to assign ICS to this site. Only an engineer
                                    assigned here (or an administrator) can manage ICS.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

SiteTeam.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
