import { Transition } from '@headlessui/react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { BadgeCheck, CheckCircle2, KeyRound, LoaderCircle, Mail, Save, ShieldCheck, Sparkles, UserRound } from 'lucide-react';
import type { FormEventHandler } from 'react';

import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { BreadcrumbItem, SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pengaturan Profil',
        href: '/settings/profile',
    },
];

export default function Profile({ mustVerifyEmail, status }: { mustVerifyEmail: boolean; status?: string }) {
    const { auth } = usePage<SharedData>().props;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: auth.user.name,
        email: auth.user.email,
    });

    const initials =
        auth.user.name
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0))
            .join('')
            .toUpperCase() || 'SS';

    const emailVerified = auth.user.email_verified_at !== null;

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        patch(route('profile.update'), {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengaturan Profil" />

            <SettingsLayout>
                <div className="profile-page space-y-6">
                    <section className="profile-overview-card relative overflow-hidden rounded-[26px] p-5 sm:p-6">
                        <div className="relative z-10 flex flex-col gap-5 sm:flex-row sm:items-center">
                            <div className="profile-avatar grid size-20 shrink-0 place-items-center rounded-[24px] text-2xl font-extrabold text-white">
                                {initials}
                            </div>

                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="profile-eyebrow inline-flex items-center gap-2 text-xs font-extrabold tracking-[0.14em] uppercase">
                                        <Sparkles className="size-4" />
                                        Profil SchoolSafe
                                    </p>

                                    <span className={emailVerified ? 'profile-badge profile-badge-success' : 'profile-badge profile-badge-warning'}>
                                        {emailVerified ? <BadgeCheck className="size-3.5" /> : <Mail className="size-3.5" />}

                                        {emailVerified ? 'Email terverifikasi' : 'Email belum diverifikasi'}
                                    </span>
                                </div>

                                <h2 className="mt-3 truncate text-2xl font-extrabold tracking-[-0.03em] text-slate-950 sm:text-3xl">
                                    {auth.user.name}
                                </h2>

                                <p className="mt-1 truncate text-sm font-medium text-slate-500">{auth.user.email}</p>
                            </div>

                            <div className="profile-account-status rounded-2xl px-4 py-3">
                                <p className="text-xs font-bold text-slate-400">Status akun</p>

                                <p className="mt-1 flex items-center gap-2 text-sm font-extrabold text-emerald-700">
                                    <span className="size-2 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.14)]" />
                                    Terhubung
                                </p>
                            </div>
                        </div>
                    </section>

                    <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(280px,0.55fr)]">
                        <section className="profile-card rounded-[26px] p-5 sm:p-6">
                            <header className="flex items-start gap-4 border-b border-slate-200/80 pb-5">
                                <span className="profile-section-icon grid size-12 shrink-0 place-items-center rounded-2xl">
                                    <UserRound className="size-6" />
                                </span>

                                <div>
                                    <h2 className="text-lg font-extrabold tracking-[-0.02em] text-slate-950">Informasi Profil</h2>

                                    <p className="mt-1 text-sm leading-6 text-slate-500">
                                        Perbarui nama lengkap dan alamat email yang digunakan untuk mengakses SchoolSafe.
                                    </p>
                                </div>
                            </header>

                            <form onSubmit={submit} className="mt-6 space-y-5">
                                <div className="profile-field grid gap-2">
                                    <Label htmlFor="name" className="font-bold text-slate-700">
                                        Nama lengkap
                                    </Label>

                                    <div className="relative">
                                        <UserRound className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />

                                        <Input
                                            id="name"
                                            className="h-12 rounded-xl pl-12"
                                            value={data.name}
                                            onChange={(event) => setData('name', event.target.value)}
                                            required
                                            autoComplete="name"
                                            placeholder="Masukkan nama lengkap"
                                        />
                                    </div>

                                    <InputError className="mt-1" message={errors.name} />
                                </div>

                                <div className="profile-field grid gap-2">
                                    <Label htmlFor="email" className="font-bold text-slate-700">
                                        Alamat email
                                    </Label>

                                    <div className="relative">
                                        <Mail className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />

                                        <Input
                                            id="email"
                                            type="email"
                                            className="h-12 rounded-xl pl-12"
                                            value={data.email}
                                            onChange={(event) => setData('email', event.target.value)}
                                            required
                                            autoComplete="username"
                                            placeholder="nama@sekolah.id"
                                        />
                                    </div>

                                    <InputError className="mt-1" message={errors.email} />
                                </div>

                                {mustVerifyEmail && !emailVerified && (
                                    <div className="profile-verification-alert rounded-2xl p-4">
                                        <div className="flex items-start gap-3">
                                            <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-700">
                                                <Mail className="size-5" />
                                            </span>

                                            <div className="min-w-0">
                                                <p className="text-sm font-extrabold text-amber-900">Verifikasi email diperlukan</p>

                                                <p className="mt-1 text-sm leading-6 text-amber-800/80">
                                                    Alamat email ini belum diverifikasi. Kirim ulang tautan verifikasi untuk mengamankan akun.
                                                </p>

                                                <Link
                                                    href={route('verification.send')}
                                                    method="post"
                                                    as="button"
                                                    className="profile-verify-button mt-3 inline-flex h-9 items-center justify-center rounded-xl px-4 text-xs font-extrabold"
                                                >
                                                    Kirim ulang verifikasi
                                                </Link>

                                                {status === 'verification-link-sent' && (
                                                    <p role="status" className="mt-3 flex items-center gap-2 text-xs font-bold text-emerald-700">
                                                        <CheckCircle2 className="size-4" />
                                                        Tautan verifikasi baru telah dikirim.
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                )}

                                <div className="flex flex-col gap-3 border-t border-slate-200/80 pt-5 sm:flex-row sm:items-center">
                                    <Button disabled={processing} className="profile-save-button h-12 rounded-xl px-5 text-sm font-extrabold">
                                        {processing ? <LoaderCircle className="size-5 animate-spin" /> : <Save className="size-5" />}

                                        {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                                    </Button>

                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition duration-300 ease-out"
                                        enterFrom="translate-y-1 opacity-0"
                                        enterTo="translate-y-0 opacity-100"
                                        leave="transition duration-200 ease-in"
                                        leaveFrom="opacity-100"
                                        leaveTo="opacity-0"
                                    >
                                        <div
                                            role="status"
                                            className="profile-saved-state inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold"
                                        >
                                            <CheckCircle2 className="size-4" />
                                            Perubahan berhasil disimpan
                                        </div>
                                    </Transition>
                                </div>
                            </form>
                        </section>

                        <aside className="profile-security-card rounded-[26px] p-5 sm:p-6">
                            <span className="profile-security-icon grid size-12 place-items-center rounded-2xl">
                                <ShieldCheck className="size-6" />
                            </span>

                            <h2 className="mt-5 text-lg font-extrabold tracking-[-0.02em] text-slate-950">Keamanan Akun</h2>

                            <p className="mt-2 text-sm leading-6 text-slate-500">
                                Pastikan informasi akun selalu benar dan kata sandi diperbarui secara berkala.
                            </p>

                            <div className="mt-5 space-y-3">
                                <div className="profile-security-row rounded-2xl p-4">
                                    <p className="text-xs font-bold text-slate-400">Status email</p>

                                    <p
                                        className={
                                            emailVerified
                                                ? 'mt-1 text-sm font-extrabold text-emerald-700'
                                                : 'mt-1 text-sm font-extrabold text-amber-700'
                                        }
                                    >
                                        {emailVerified ? 'Terverifikasi' : 'Belum terverifikasi'}
                                    </p>
                                </div>

                                <div className="profile-security-row rounded-2xl p-4">
                                    <p className="text-xs font-bold text-slate-400">Akses akun</p>

                                    <p className="mt-1 text-sm font-extrabold text-slate-800">Dilindungi kata sandi</p>
                                </div>
                            </div>

                            <Link
                                href="/settings/password"
                                className="profile-security-link mt-5 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl px-4 text-sm font-extrabold"
                            >
                                <KeyRound className="size-4" />
                                Kelola kata sandi
                            </Link>
                        </aside>
                    </div>

                    <DeleteUser />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
