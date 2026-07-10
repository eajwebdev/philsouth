import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Lock, Mail } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';

/* ------------------------------------------------------------------ */
/* Animated construction scene (pure CSS/SVG, honors reduced motion)  */
/* ------------------------------------------------------------------ */

function Crane({ className, flip = false, duration = 9 }: { className?: string; flip?: boolean; duration?: number }) {
    return (
        <svg
            viewBox="0 0 220 300"
            className={className}
            style={flip ? { transform: 'scaleX(-1)' } : undefined}
            aria-hidden
        >
            {/* mast */}
            <rect x="96" y="60" width="12" height="240" fill="currentColor" opacity="0.9" />
            <path d="M96 300 96 60 108 300 M108 300 108 60 96 300" stroke="currentColor" strokeWidth="1.5" opacity="0.5" />
            {/* slewing group — sways around the mast top */}
            <g style={{ transformOrigin: '102px 56px', animation: `ps-sway ${duration}s ease-in-out infinite` }}>
                {/* jib */}
                <rect x="102" y="50" width="112" height="7" fill="currentColor" />
                {/* counter-jib + weight */}
                <rect x="40" y="50" width="62" height="7" fill="currentColor" />
                <rect x="36" y="57" width="20" height="16" fill="currentColor" />
                {/* apex + tie lines */}
                <path d="M102 20 L214 52 M102 20 L44 52 M102 20 L102 50" stroke="currentColor" strokeWidth="2" fill="none" />
                <rect x="98" y="16" width="8" height="36" fill="currentColor" />
                {/* trolley, cable and hook — hook slowly lowers */}
                <g style={{ animation: `ps-hook ${duration * 1.4}s ease-in-out infinite` }}>
                    <rect x="168" y="57" width="12" height="6" fill="currentColor" />
                    <line x1="174" y1="63" x2="174" y2="120" stroke="currentColor" strokeWidth="1.5" />
                    <path d="M174 120 q -7 8 0 12 q 6 -4 0 -12" stroke="currentColor" strokeWidth="2.5" fill="none" />
                    {/* the lifted beam glows gold */}
                    <rect x="158" y="132" width="32" height="7" rx="1.5" fill="#E5B65A" opacity="0.95" />
                </g>
                {/* warning light */}
                <circle cx="102" cy="14" r="3" fill="#E5B65A" style={{ animation: 'ps-blink 4s infinite' }} />
            </g>
            {/* operator cab */}
            <rect x="90" y="58" width="24" height="16" rx="2" fill="currentColor" />
        </svg>
    );
}

function Skyline() {
    return (
        <svg viewBox="0 0 1440 240" preserveAspectRatio="xMidYMax slice" className="absolute inset-x-0 bottom-0 h-40 w-full text-[#241608] sm:h-56" aria-hidden>
            {/* buildings in three depths */}
            <g opacity="0.55">
                <rect x="40" y="120" width="90" height="120" fill="currentColor" />
                <rect x="210" y="90" width="70" height="150" fill="currentColor" />
                <rect x="330" y="140" width="120" height="100" fill="currentColor" />
                <rect x="980" y="100" width="90" height="140" fill="currentColor" />
                <rect x="1300" y="130" width="110" height="110" fill="currentColor" />
            </g>
            <g opacity="0.8">
                <rect x="120" y="60" width="80" height="180" fill="currentColor" />
                <rect x="500" y="80" width="100" height="160" fill="currentColor" />
                <rect x="1080" y="60" width="80" height="180" fill="currentColor" />
                <rect x="1210" y="100" width="70" height="140" fill="currentColor" />
            </g>
            {/* building under construction: floors + scaffolding */}
            <g>
                <rect x="640" y="110" width="180" height="130" fill="currentColor" />
                <path d="M640 140 h180 M640 170 h180 M640 200 h180 M670 110 v130 M700 110 v130 M730 110 v130 M760 110 v130 M790 110 v130" stroke="#B8792B" strokeWidth="1" opacity="0.35" />
                <rect x="628" y="104" width="204" height="8" fill="currentColor" />
            </g>
            {/* lit windows */}
            <g fill="#E5B65A">
                <rect x="140" y="80" width="8" height="8" opacity="0.9" style={{ animation: 'ps-blink 6s infinite' }} />
                <rect x="164" y="110" width="8" height="8" opacity="0.6" />
                <rect x="520" y="100" width="8" height="8" opacity="0.7" style={{ animation: 'ps-blink 7s 1s infinite' }} />
                <rect x="556" y="130" width="8" height="8" opacity="0.5" />
                <rect x="1096" y="84" width="8" height="8" opacity="0.8" style={{ animation: 'ps-blink 5s 2s infinite' }} />
                <rect x="1120" y="120" width="8" height="8" opacity="0.5" />
                <rect x="1330" y="150" width="8" height="8" opacity="0.6" />
            </g>
            {/* ground line */}
            <rect x="0" y="236" width="1440" height="4" fill="currentColor" />
        </svg>
    );
}

/** Floating gold dust motes rising from the site. */
function Motes() {
    const motes = [
        { left: '8%', size: 5, delay: 0, dur: 16 },
        { left: '18%', size: 3, delay: 4, dur: 20 },
        { left: '29%', size: 4, delay: 9, dur: 17 },
        { left: '43%', size: 3, delay: 2, dur: 22 },
        { left: '57%', size: 5, delay: 7, dur: 18 },
        { left: '68%', size: 3, delay: 12, dur: 21 },
        { left: '79%', size: 4, delay: 5, dur: 16 },
        { left: '90%', size: 3, delay: 10, dur: 19 },
    ];
    return (
        <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden>
            {motes.map((m, i) => (
                <span
                    key={i}
                    className="absolute bottom-24 rounded-full bg-[#E5B65A]"
                    style={{
                        left: m.left,
                        width: m.size,
                        height: m.size,
                        opacity: 0,
                        animation: `ps-rise ${m.dur}s ${m.delay}s linear infinite`,
                    }}
                />
            ))}
        </div>
    );
}

/* ------------------------------------------------------------------ */

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
            <div className="login-scene relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-b from-[#1d1107] via-[#2c1a0b] to-[#3B2814] px-4 py-10">
                {/* drifting blueprint grid */}
                <div
                    className="absolute inset-0 opacity-[0.07]"
                    style={{
                        backgroundImage:
                            'linear-gradient(#F2DDA4 1px, transparent 1px), linear-gradient(90deg, #F2DDA4 1px, transparent 1px)',
                        backgroundSize: '40px 40px',
                        animation: 'ps-drift 40s linear infinite',
                    }}
                    aria-hidden
                />
                {/* warm glow behind the card */}
                <div className="pointer-events-none absolute left-1/2 top-1/2 size-[560px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#B8792B]/20 blur-[110px]" aria-hidden />

                {/* cranes */}
                <Crane className="absolute -left-8 bottom-24 hidden h-[46vh] max-h-[420px] text-[#2a1a0c] md:block" duration={10} />
                <Crane className="absolute -right-10 bottom-24 hidden h-[34vh] max-h-[320px] text-[#241608] lg:block" flip duration={13} />

                <Skyline />
                <Motes />

                {/* centered login card */}
                <div className="relative z-10 w-full max-w-md">
                    <div className="animate-fade-up rounded-2xl border border-white/10 bg-white/[0.07] p-8 shadow-2xl shadow-black/40 backdrop-blur-xl sm:p-10">
                        <div className="mb-8 flex flex-col items-center text-center">
                            <div className="animate-float">
                                <img
                                    src="/logo.jpg"
                                    alt="PhilSouth Builders"
                                    className="size-20 rounded-2xl object-contain shadow-lg shadow-black/40 ring-2 ring-[#E5B65A]/40"
                                />
                            </div>
                            <h1 className="animate-fade-up delay-150 mt-5 text-2xl font-bold tracking-tight text-white">
                                PhilSouth Builders
                            </h1>
                            <p className="animate-fade-up delay-200 mt-1 text-sm text-[#F2DDA4]/70">
                                Site Inventory System
                            </p>
                        </div>

                        {status && (
                            <div className="animate-fade-in mb-4 rounded-lg border border-[#E5B65A]/30 bg-[#E5B65A]/10 px-4 py-2 text-sm text-[#F2DDA4]">
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="flex flex-col gap-5">
                            <div className="animate-fade-up delay-200 grid gap-2">
                                <Label htmlFor="email" className="text-[#F2DDA4]/90">Email</Label>
                                <div className="relative">
                                    <Mail className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#F2DDA4]/40" />
                                    <Input
                                        id="email"
                                        type="email"
                                        autoComplete="username"
                                        autoFocus
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="you@philsouth.test"
                                        aria-invalid={!!errors.email}
                                        className="h-11 border-white/15 bg-white/[0.06] pl-10 text-white placeholder:text-white/30 focus-visible:border-[#E5B65A]/60 focus-visible:ring-[#E5B65A]/30"
                                    />
                                </div>
                                {errors.email && <p className="text-sm text-[#ff9f7a]">{errors.email}</p>}
                            </div>

                            <div className="animate-fade-up delay-300 grid gap-2">
                                <Label htmlFor="password" className="text-[#F2DDA4]/90">Password</Label>
                                <div className="relative">
                                    <Lock className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#F2DDA4]/40" />
                                    <Input
                                        id="password"
                                        type="password"
                                        autoComplete="current-password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="••••••••"
                                        aria-invalid={!!errors.password}
                                        className="h-11 border-white/15 bg-white/[0.06] pl-10 text-white placeholder:text-white/30 focus-visible:border-[#E5B65A]/60 focus-visible:ring-[#E5B65A]/30"
                                    />
                                </div>
                                {errors.password && <p className="text-sm text-[#ff9f7a]">{errors.password}</p>}
                            </div>

                            <label className="animate-fade-up delay-300 flex items-center gap-2 text-sm text-[#F2DDA4]/70">
                                <Checkbox
                                    checked={data.remember}
                                    onCheckedChange={(v) => setData('remember', v === true)}
                                    className="border-white/25 data-[state=checked]:border-[#B8792B] data-[state=checked]:bg-[#B8792B]"
                                />
                                Keep me signed in
                            </label>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="animate-fade-up delay-500 h-11 w-full bg-gradient-to-r from-[#B8792B] to-[#E5B65A] text-base font-semibold text-[#2a1a0c] shadow-lg shadow-[#B8792B]/25 transition-all hover:from-[#c98a35] hover:to-[#eec269] hover:shadow-[#B8792B]/40 focus-visible:ring-[#E5B65A]/50"
                            >
                                {processing && <LoaderCircle className="size-4 animate-spin" />}
                                Sign in
                            </Button>
                        </form>
                    </div>

                    <p className="animate-fade-in delay-500 mt-6 text-center text-xs text-[#F2DDA4]/40">
                        © {new Date().getFullYear()} PhilSouth Builders Inc. · Building with integrity
                    </p>
                </div>
            </div>
        </>
    );
}
