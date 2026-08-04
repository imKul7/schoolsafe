import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LockKeyhole, LogIn, Mail, ShieldCheck } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/auth-layout';

interface LoginProps {
    status?: string;
    canResetPassword?: boolean;
}
interface LoginForm {
    [key: string]: string | boolean;
    email: string;
    password: string;
    remember: boolean;
}

export default function Login({ status, canResetPassword = true }: LoginProps) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<LoginForm>({ email: '', password: '', remember: false });

    const submit: FormEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        clearErrors();
        post('/login', { preserveScroll: true, onFinish: () => reset('password') });
    };

    return (
        <AuthLayout title="Selamat datang kembali" description="Masuk dengan akun sekolah Anda untuk melanjutkan ke SchoolSafe.">
            <Head title="Masuk" />
            {status && (
                <div
                    role="status"
                    aria-live="polite"
                    className="mb-5 flex items-start gap-3 rounded-xl border border-[#b9e4dc] bg-[#effaf7] px-4 py-3 text-sm text-[#276a61]"
                >
                    <ShieldCheck className="mt-0.5 size-5 shrink-0" />
                    <span className="leading-6">{status}</span>
                </div>
            )}

            <form onSubmit={submit} className="space-y-5" aria-busy={processing} noValidate>
                <div>
                    <label htmlFor="email" className="mb-2 block text-sm font-bold text-[#284762]">
                        Alamat email
                    </label>
                    <div className="relative">
                        <Mail className="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-[#829ab1]" />
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            value={data.email}
                            onChange={(event) => {
                                setData('email', event.currentTarget.value);
                                if (errors.email) clearErrors('email');
                            }}
                            autoComplete="email"
                            autoFocus
                            disabled={processing}
                            placeholder="admin@sekolah.com"
                            aria-invalid={Boolean(errors.email)}
                            aria-describedby={errors.email ? 'email-error' : undefined}
                            className={`h-12 rounded-xl border bg-[#fbfdff] pl-11 text-[#17324d] transition placeholder:text-[#9aabba] focus-visible:border-[#5b8def] focus-visible:ring-[#bed4fb] disabled:cursor-not-allowed disabled:opacity-70 ${errors.email ? 'border-[#df7a72]' : 'border-[#d6e2eb]'}`}
                        />
                    </div>
                    <div id="email-error">
                        <InputError message={errors.email} className="mt-2" />
                    </div>
                </div>

                <div>
                    <div className="mb-2 flex items-center justify-between gap-4">
                        <label htmlFor="password" className="text-sm font-bold text-[#284762]">
                            Kata sandi
                        </label>
                        {canResetPassword && (
                            <Link
                                href="/forgot-password"
                                className="text-xs font-bold text-[#4d79d4] transition hover:text-[#315fae] hover:underline"
                            >
                                Lupa kata sandi?
                            </Link>
                        )}
                    </div>
                    <div className="relative">
                        <LockKeyhole className="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-[#829ab1]" />
                        <Input
                            id="password"
                            name="password"
                            type={showPassword ? 'text' : 'password'}
                            value={data.password}
                            onChange={(event) => {
                                setData('password', event.currentTarget.value);
                                if (errors.password) clearErrors('password');
                            }}
                            autoComplete="current-password"
                            disabled={processing}
                            placeholder="Masukkan kata sandi"
                            aria-invalid={Boolean(errors.password)}
                            aria-describedby={errors.password ? 'password-error' : undefined}
                            className={`h-12 rounded-xl border bg-[#fbfdff] px-11 text-[#17324d] transition placeholder:text-[#9aabba] focus-visible:border-[#5b8def] focus-visible:ring-[#bed4fb] disabled:cursor-not-allowed disabled:opacity-70 ${errors.password ? 'border-[#df7a72]' : 'border-[#d6e2eb]'}`}
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword((current) => !current)}
                            disabled={processing}
                            aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                            aria-pressed={showPassword}
                            className="absolute top-1/2 right-3 grid size-8 -translate-y-1/2 place-items-center rounded-lg text-[#829ab1] transition hover:bg-[#edf3fa] hover:text-[#4d79d4] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {showPassword ? <EyeOff className="size-5" /> : <Eye className="size-5" />}
                        </button>
                    </div>
                    <div id="password-error">
                        <InputError message={errors.password} className="mt-2" />
                    </div>
                </div>

                <label htmlFor="remember" className="flex w-fit cursor-pointer items-center gap-3">
                    <input
                        id="remember"
                        name="remember"
                        type="checkbox"
                        checked={data.remember}
                        onChange={(event) => setData('remember', event.currentTarget.checked)}
                        disabled={processing}
                        className="size-4 cursor-pointer rounded border-[#bcccdc] accent-[#5b8def] focus:ring-2 focus:ring-[#cfe1f3] disabled:cursor-not-allowed disabled:opacity-60"
                    />
                    <span className="text-sm text-[#617990] select-none">Ingat saya di perangkat ini</span>
                </label>

                <Button
                    type="submit"
                    disabled={processing}
                    className="h-12 w-full rounded-xl bg-[#5b8def] font-bold text-white shadow-lg shadow-[#5b8def]/25 transition-all hover:-translate-y-0.5 hover:bg-[#4979da] hover:shadow-xl disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-70"
                >
                    {processing ? (
                        <span className="flex items-center gap-2">
                            <span className="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" />
                            Memproses...
                        </span>
                    ) : (
                        <span className="flex items-center gap-2">
                            <LogIn className="size-4" />
                            Masuk ke SchoolSafe
                        </span>
                    )}
                </Button>
            </form>

            <div className="mt-7 flex items-center gap-3">
                <div className="h-px flex-1 bg-[#e0eaf1]" />
                <span className="text-xs font-medium whitespace-nowrap text-[#829ab1]">Akses aman untuk sekolah</span>
                <div className="h-px flex-1 bg-[#e0eaf1]" />
            </div>
            <div className="mt-5 flex gap-3 rounded-2xl border border-[#dbe7ef] bg-[#f7fafc] p-4">
                <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-[#e9f1ff] text-[#4d79d4]">
                    <ShieldCheck className="size-5" />
                </span>
                <div>
                    <p className="text-sm font-bold text-[#284762]">Akses terlindungi</p>
                    <p className="mt-1 text-xs leading-5 text-[#617990]">
                        Gunakan akun resmi yang diberikan administrator sekolah dan jangan membagikan kata sandi Anda.
                    </p>
                </div>
            </div>
        </AuthLayout>
    );
}
