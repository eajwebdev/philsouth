import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { AppLogo } from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';

export default function Login({ status }: { status?: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Sign in" />
            <div className="grid min-h-screen lg:grid-cols-2">
                {/* Brand panel */}
                <div className="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-[#3B2814] via-[#8B5A2B] to-[#B8792B] p-10 text-white lg:flex">
                    <AppLogo className="[&_span]:text-white [&_img]:ring-white/30" />
                    <div className="relative z-10 max-w-md">
                        <h2 className="text-3xl font-bold leading-tight">
                            Site inventory, digitized.
                        </h2>
                        <p className="mt-3 text-white/80">
                            Withdrawal slips, stock cards, transfers and monthly summaries —
                            one source of truth across every PhilSouth project site.
                        </p>
                    </div>
                    <p className="relative z-10 text-sm text-white/60">
                        © {new Date().getFullYear()} PhilSouth Builders Inc.
                    </p>
                    <div className="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full bg-white/10 blur-2xl" />
                    <div className="pointer-events-none absolute -bottom-32 -left-16 size-80 rounded-full bg-black/20 blur-2xl" />
                </div>

                {/* Form */}
                <div className="flex items-center justify-center p-6 sm:p-10">
                    <div className="w-full max-w-sm">
                        <div className="mb-8 flex flex-col items-center gap-4 lg:hidden">
                            <AppLogo />
                        </div>
                        <div className="mb-6">
                            <h1 className="text-2xl font-semibold tracking-tight">Welcome back</h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Sign in to the inventory system.
                            </p>
                        </div>

                        {status && (
                            <div className="mb-4 rounded-lg bg-success/10 px-4 py-2 text-sm text-success">
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="flex flex-col gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    autoComplete="username"
                                    autoFocus
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="you@philsouth.test"
                                    aria-invalid={!!errors.email}
                                />
                                {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="••••••••"
                                    aria-invalid={!!errors.password}
                                />
                                {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
                            </div>

                            <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Checkbox
                                    checked={data.remember}
                                    onCheckedChange={(v) => setData('remember', v === true)}
                                />
                                Remember me
                            </label>

                            <Button type="submit" className="w-full" disabled={processing}>
                                {processing && <LoaderCircle className="size-4 animate-spin" />}
                                Sign in
                            </Button>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}
