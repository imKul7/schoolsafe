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

/**
 * Index signature diperlukan agar sesuai dengan FormDataType
 * dari useForm milik Inertia.
 */
interface LoginForm {
    [key: string]: string | boolean;

    email: string;
    password: string;
    remember: boolean;
}

export default function Login({ status, canResetPassword = true }: LoginProps) {
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();

        clearErrors();

        post('/login', {
            preserveScroll: true,
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Selamat datang kembali" description="Masukkan akun sekolah Anda untuk mengakses sistem penjemputan SchoolSafe.">
            <Head title="Masuk" />

            {status && (
                <div
                    role="status"
                    aria-live="polite"
                    className="mb-5 flex items-start gap-3 rounded-2xl border border-[#cde9e3] bg-[#eef9f6] px-4 py-3 text-sm text-[#397a72]"
                >
                    <ShieldCheck aria-hidden="true" className="mt-0.5 size-5 shrink-0" />

                    <span className="leading-6">{status}</span>
                </div>
            )}

            <form onSubmit={submit} className="space-y-5" aria-busy={processing} noValidate>
                {/* Email */}
                <div>
                    <label htmlFor="email" className="mb-2 block text-sm font-semibold text-[#334e68]">
                        Alamat email
                    </label>

                    <div className="relative">
                        <Mail aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-[#9fb3c8]" />

                        <Input
                            id="email"
                            name="email"
                            type="email"
                            value={data.email}
                            onChange={(event) => {
                                setData('email', event.currentTarget.value);

                                if (errors.email) {
                                    clearErrors('email');
                                }
                            }}
                            autoComplete="email"
                            autoFocus
                            disabled={processing}
                            placeholder="admin@sekolah.com"
                            aria-invalid={Boolean(errors.email)}
                            aria-describedby={errors.email ? 'email-error' : undefined}
                            className={[
                                'h-12 rounded-xl bg-[#fbfdff] pl-11',
                                'text-[#334e68] placeholder:text-[#bcccdc]',
                                'transition-colors',
                                'focus-visible:ring-[#dcebf8]',
                                'disabled:cursor-not-allowed disabled:opacity-70',
                                errors.email ? 'border-[#e97a7a] focus-visible:border-[#e97a7a]' : 'border-[#d9e5ee] focus-visible:border-[#7fa9d8]',
                            ].join(' ')}
                        />
                    </div>

                    <div id="email-error">
                        <InputError message={errors.email} className="mt-2" />
                    </div>
                </div>

                {/* Password */}
                <div>
                    <div className="mb-2 flex items-center justify-between gap-4">
                        <label htmlFor="password" className="text-sm font-semibold text-[#334e68]">
                            Kata sandi
                        </label>

                        {canResetPassword && (
                            <Link
                                href="/forgot-password"
                                className="text-xs font-semibold text-[#4f7cac] transition-colors hover:text-[#37658f] hover:underline focus-visible:rounded focus-visible:ring-2 focus-visible:ring-[#dcebf8] focus-visible:outline-none"
                            >
                                Lupa kata sandi?
                            </Link>
                        )}
                    </div>

                    <div className="relative">
                        <LockKeyhole
                            aria-hidden="true"
                            className="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-[#9fb3c8]"
                        />

                        <Input
                            id="password"
                            name="password"
                            type={showPassword ? 'text' : 'password'}
                            value={data.password}
                            onChange={(event) => {
                                setData('password', event.currentTarget.value);

                                if (errors.password) {
                                    clearErrors('password');
                                }
                            }}
                            autoComplete="current-password"
                            disabled={processing}
                            placeholder="Masukkan kata sandi"
                            aria-invalid={Boolean(errors.password)}
                            aria-describedby={errors.password ? 'password-error' : undefined}
                            className={[
                                'h-12 rounded-xl bg-[#fbfdff] px-11',
                                'text-[#334e68] placeholder:text-[#bcccdc]',
                                'transition-colors',
                                'focus-visible:ring-[#dcebf8]',
                                'disabled:cursor-not-allowed disabled:opacity-70',
                                errors.password
                                    ? 'border-[#e97a7a] focus-visible:border-[#e97a7a]'
                                    : 'border-[#d9e5ee] focus-visible:border-[#7fa9d8]',
                            ].join(' ')}
                        />

                        <button
                            type="button"
                            onClick={() => {
                                setShowPassword((current) => !current);
                            }}
                            disabled={processing}
                            aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                            aria-pressed={showPassword}
                            className="absolute top-1/2 right-3.5 flex size-8 -translate-y-1/2 items-center justify-center rounded-lg text-[#9fb3c8] transition-colors hover:bg-[#eef6ff] hover:text-[#4f7cac] focus-visible:ring-2 focus-visible:ring-[#dcebf8] focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {showPassword ? <EyeOff aria-hidden="true" className="size-5" /> : <Eye aria-hidden="true" className="size-5" />}
                        </button>
                    </div>

                    <div id="password-error">
                        <InputError message={errors.password} className="mt-2" />
                    </div>
                </div>

                {/* Remember me */}
                <label htmlFor="remember" className="flex w-fit cursor-pointer items-center gap-3">
                    <input
                        id="remember"
                        name="remember"
                        type="checkbox"
                        checked={data.remember}
                        onChange={(event) => {
                            setData('remember', event.currentTarget.checked);
                        }}
                        disabled={processing}
                        className="size-4 cursor-pointer rounded border-[#bcccdc] accent-[#5b8def] focus:ring-2 focus:ring-[#cfe1f3] disabled:cursor-not-allowed disabled:opacity-60"
                    />

                    <span className="text-sm text-[#627d98] select-none">Ingat saya di perangkat ini</span>
                </label>

                {/* Submit */}
                <Button
                    type="submit"
                    disabled={processing}
                    className="h-12 w-full rounded-xl bg-[#5b8def] font-semibold text-white shadow-lg shadow-blue-200/60 transition-all hover:-translate-y-0.5 hover:bg-[#4c7fd9] hover:shadow-xl focus-visible:ring-2 focus-visible:ring-[#bdd7f3] disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-70"
                >
                    {processing ? (
                        <span className="flex items-center gap-2">
                            <span aria-hidden="true" className="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" />

                            <span>Memproses...</span>
                        </span>
                    ) : (
                        <span className="flex items-center gap-2">
                            <LogIn aria-hidden="true" className="size-4" />

                            <span>Masuk ke SchoolSafe</span>
                        </span>
                    )}
                </Button>
            </form>

            {/* Divider */}
            <div className="mt-7 flex items-center gap-3">
                <div className="h-px flex-1 bg-[#e6eef5]" />

                <span className="text-xs whitespace-nowrap text-[#9fb3c8]">Sistem penjemputan aman</span>

                <div className="h-px flex-1 bg-[#e6eef5]" />
            </div>

            {/* Security information */}
            <div className="mt-5 rounded-2xl border border-[#e6eef5] bg-[#f8fbfd] p-4">
                <div className="flex items-start gap-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#e8f6f3] text-[#4c9e94]">
                        <ShieldCheck aria-hidden="true" className="size-5" />
                    </div>

                    <div>
                        <p className="text-sm font-semibold text-[#334e68]">Akses terlindungi</p>

                        <p className="mt-1 text-xs leading-5 text-[#829ab1]">
                            Gunakan akun resmi yang telah diberikan oleh administrator sekolah. Jangan membagikan kata sandi kepada orang lain.
                        </p>
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}
