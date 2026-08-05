import { Activity, ArrowUpRight, Clock3, type LucideIcon } from 'lucide-react';
import { type CSSProperties, type ReactNode, useEffect, useId, useState } from 'react';

type StatTone = 'blue' | 'emerald' | 'amber' | 'rose';

interface AnimatedNumberProps {
    value: number;
    className?: string;
    duration?: number;
}

interface DashboardStatCardProps {
    label: string;
    value: number;
    description: string;
    icon: LucideIcon;
    tone: StatTone;
    isLoading?: boolean;
    delay?: number;
    emptyMessage?: string;
}

interface ProgressRingProps {
    value: number;
    label: string;
    size?: number;
    strokeWidth?: number;
}

interface MetricTileProps {
    label: string;
    value: number;
    icon: LucideIcon;
    tone: 'blue' | 'emerald' | 'rose';
}

interface DashboardEmptyStateProps {
    title: string;
    description: string;
    icon?: LucideIcon;
    action?: ReactNode;
}

const numberFormatter = new Intl.NumberFormat('id-ID');

const statToneClasses: Record<
    StatTone,
    {
        accent: string;
        icon: string;
        glow: string;
        badge: string;
    }
> = {
    blue: {
        accent: 'from-blue-500 via-indigo-500 to-cyan-400',
        icon: 'bg-blue-50 text-blue-600 ring-blue-100 group-hover:bg-blue-600 group-hover:text-white',
        glow: 'bg-blue-400/15',
        badge: 'bg-blue-50 text-blue-700 ring-blue-100',
    },

    emerald: {
        accent: 'from-emerald-500 via-teal-500 to-cyan-400',
        icon: 'bg-emerald-50 text-emerald-600 ring-emerald-100 group-hover:bg-emerald-600 group-hover:text-white',
        glow: 'bg-emerald-400/15',
        badge: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
    },

    amber: {
        accent: 'from-amber-400 via-orange-400 to-rose-400',
        icon: 'bg-amber-50 text-amber-600 ring-amber-100 group-hover:bg-amber-500 group-hover:text-white',
        glow: 'bg-amber-400/15',
        badge: 'bg-amber-50 text-amber-700 ring-amber-100',
    },

    rose: {
        accent: 'from-rose-500 via-pink-500 to-orange-400',
        icon: 'bg-rose-50 text-rose-600 ring-rose-100 group-hover:bg-rose-500 group-hover:text-white',
        glow: 'bg-rose-400/15',
        badge: 'bg-rose-50 text-rose-700 ring-rose-100',
    },
};

const metricToneClasses = {
    blue: {
        wrapper: 'border-blue-100 bg-blue-50/80',
        icon: 'bg-blue-600 text-white shadow-blue-500/20',
        value: 'text-blue-700',
    },

    emerald: {
        wrapper: 'border-emerald-100 bg-emerald-50/80',
        icon: 'bg-emerald-600 text-white shadow-emerald-500/20',
        value: 'text-emerald-700',
    },

    rose: {
        wrapper: 'border-rose-100 bg-rose-50/80',
        icon: 'bg-rose-500 text-white shadow-rose-500/20',
        value: 'text-rose-700',
    },
} as const;

function safeNumber(value: number): number {
    return Number.isFinite(value) ? Math.max(0, value) : 0;
}

function safePercentage(value: number): number {
    return Math.min(100, Math.max(0, safeNumber(value)));
}

function prefersReducedMotion(): boolean {
    return typeof window !== 'undefined' && typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

export function AnimatedNumber({ value, className, duration = 850 }: AnimatedNumberProps) {
    const target = safeNumber(value);
    const [displayedValue, setDisplayedValue] = useState(0);

    useEffect(() => {
        if (prefersReducedMotion()) {
            setDisplayedValue(target);

            return;
        }

        let animationFrame = 0;
        const startedAt = performance.now();

        const animate = (currentTime: number) => {
            const elapsed = currentTime - startedAt;
            const progress = Math.min(1, elapsed / duration);
            const easedProgress = 1 - Math.pow(1 - progress, 3);

            setDisplayedValue(Math.round(target * easedProgress));

            if (progress < 1) {
                animationFrame = window.requestAnimationFrame(animate);
            }
        };

        animationFrame = window.requestAnimationFrame(animate);

        return () => {
            window.cancelAnimationFrame(animationFrame);
        };
    }, [duration, target]);

    return (
        <span className={className} aria-label={numberFormatter.format(target)}>
            <span aria-hidden="true">{numberFormatter.format(displayedValue)}</span>

            <span className="sr-only">{numberFormatter.format(target)}</span>
        </span>
    );
}

export function DashboardStatCard({
    label,
    value,
    description,
    icon: Icon,
    tone,
    isLoading = false,
    delay = 0,
    emptyMessage,
}: DashboardStatCardProps) {
    const toneClasses = statToneClasses[tone];
    const safeValue = safeNumber(value);

    const animationStyle = {
        animationDelay: `${delay}ms`,
    } satisfies CSSProperties;

    return (
        <article
            className="dashboard-reveal group relative min-h-48 overflow-hidden rounded-2xl border border-white/80 bg-white/85 p-5 shadow-[0_12px_35px_rgba(15,23,42,0.07)] ring-1 ring-slate-200/60 backdrop-blur-xl transition-all duration-300 ease-out hover:-translate-y-1.5 hover:scale-[1.015] hover:shadow-[0_22px_55px_rgba(37,99,235,0.14)]"
            style={animationStyle}
        >
            <span className={`absolute inset-x-5 top-0 h-1 rounded-b-full bg-gradient-to-r ${toneClasses.accent}`} aria-hidden="true" />

            <span
                className={`pointer-events-none absolute -top-16 -right-16 size-36 rounded-full blur-3xl transition-transform duration-500 group-hover:scale-125 ${toneClasses.glow}`}
                aria-hidden="true"
            />

            <div className="relative flex h-full flex-col">
                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <p className="text-[11px] font-bold tracking-[0.15em] text-slate-500 uppercase">{label}</p>

                        {isLoading ? (
                            <div className="mt-4 h-10 w-28 animate-pulse rounded-xl bg-slate-200/80" aria-hidden="true" />
                        ) : (
                            <AnimatedNumber value={safeValue} className="mt-3 block text-4xl font-extrabold tracking-[-0.04em] text-slate-900" />
                        )}
                    </div>

                    <span
                        className={`grid size-12 shrink-0 place-items-center rounded-2xl shadow-sm ring-1 transition-all duration-300 group-hover:scale-110 group-hover:-rotate-3 ${toneClasses.icon}`}
                    >
                        <Icon className="size-5" strokeWidth={2} aria-hidden="true" />
                    </span>
                </div>

                <div className="mt-auto pt-6">
                    {isLoading ? (
                        <div className="space-y-2" aria-hidden="true">
                            <div className="h-3 w-full animate-pulse rounded-full bg-slate-200/80" />
                            <div className="h-3 w-2/3 animate-pulse rounded-full bg-slate-200/70" />
                        </div>
                    ) : (
                        <>
                            <p className="text-sm leading-6 font-medium text-slate-600">
                                {safeValue === 0 && emptyMessage ? emptyMessage : description}
                            </p>

                            <span
                                className={`mt-3 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold tracking-wide uppercase ring-1 ${toneClasses.badge}`}
                            >
                                <Activity className="size-3" strokeWidth={2} aria-hidden="true" />
                                Data langsung
                            </span>
                        </>
                    )}
                </div>
            </div>
        </article>
    );
}

export function ProgressRing({ value, label, size = 148, strokeWidth = 11 }: ProgressRingProps) {
    const gradientId = useId();
    const percent = safePercentage(value);
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const [animatedPercent, setAnimatedPercent] = useState(0);

    useEffect(() => {
        if (prefersReducedMotion()) {
            setAnimatedPercent(percent);

            return;
        }

        const frame = window.requestAnimationFrame(() => {
            setAnimatedPercent(percent);
        });

        return () => window.cancelAnimationFrame(frame);
    }, [percent]);

    const dashOffset = circumference - (animatedPercent / 100) * circumference;

    return (
        <div
            className="relative grid shrink-0 place-items-center"
            style={{ width: size, height: size }}
            role="progressbar"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={percent}
        >
            <svg
                width={size}
                height={size}
                viewBox={`0 0 ${size} ${size}`}
                className="-rotate-90 drop-shadow-[0_10px_20px_rgba(20,184,166,0.15)]"
                aria-hidden="true"
            >
                <defs>
                    <linearGradient id={gradientId} x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="#2563eb" />
                        <stop offset="48%" stopColor="#4f46e5" />
                        <stop offset="100%" stopColor="#14b8a6" />
                    </linearGradient>
                </defs>

                <circle cx={size / 2} cy={size / 2} r={radius} fill="transparent" stroke="rgb(226 232 240 / 0.8)" strokeWidth={strokeWidth} />

                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="transparent"
                    stroke={`url(#${gradientId})`}
                    strokeWidth={strokeWidth}
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={dashOffset}
                    className="transition-[stroke-dashoffset] duration-1000 ease-out"
                />
            </svg>

            <div className="absolute inset-0 grid place-items-center text-center">
                <div>
                    <strong className="block text-3xl font-extrabold tracking-tight text-slate-900">{percent}%</strong>

                    <span className="mt-0.5 block text-[10px] font-bold tracking-[0.14em] text-slate-500 uppercase">Siap</span>
                </div>
            </div>
        </div>
    );
}

export function MetricTile({ label, value, icon: Icon, tone }: MetricTileProps) {
    const toneClasses = metricToneClasses[tone];

    return (
        <div className={`rounded-2xl border p-3.5 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md ${toneClasses.wrapper}`}>
            <div className="flex items-center justify-between gap-3">
                <span className={`grid size-8 place-items-center rounded-xl shadow-lg ${toneClasses.icon}`}>
                    <Icon className="size-4" strokeWidth={2} aria-hidden="true" />
                </span>

                <AnimatedNumber value={value} className={`text-xl font-extrabold tracking-tight ${toneClasses.value}`} duration={650} />
            </div>

            <p className="mt-3 text-[10px] font-bold tracking-[0.12em] text-slate-500 uppercase">{label}</p>
        </div>
    );
}

export function DashboardEmptyState({ title, description, icon: Icon = Clock3, action }: DashboardEmptyStateProps) {
    return (
        <div className="relative overflow-hidden rounded-2xl border border-dashed border-blue-200/80 bg-gradient-to-br from-blue-50/80 via-white to-cyan-50/70 px-6 py-9 text-center">
            <span
                className="pointer-events-none absolute top-0 left-1/2 size-32 -translate-x-1/2 -translate-y-1/2 rounded-full bg-blue-300/20 blur-3xl"
                aria-hidden="true"
            />

            <span className="relative mx-auto grid size-14 place-items-center rounded-2xl bg-white text-blue-600 shadow-lg ring-1 shadow-blue-500/10 ring-blue-100">
                <Icon className="size-6" strokeWidth={1.9} aria-hidden="true" />
            </span>

            <h3 className="relative mt-4 text-base font-extrabold tracking-tight text-slate-900">{title}</h3>

            <p className="relative mx-auto mt-1.5 max-w-md text-sm leading-6 text-slate-600">{description}</p>

            {action && <div className="relative mt-5">{action}</div>}
        </div>
    );
}

export function DashboardSectionLink({ href, children }: { href: string; children: ReactNode }) {
    return (
        <a
            href={href}
            className="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-bold text-blue-600 transition-all duration-200 hover:bg-blue-50 hover:text-blue-700"
        >
            {children}

            <ArrowUpRight className="size-4" strokeWidth={2} aria-hidden="true" />
        </a>
    );
}
