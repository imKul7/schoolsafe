import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BadgeCheck,
    Camera,
    CheckCircle2,
    Clock3,
    KeyRound,
    LoaderCircle,
    Mail,
    Settings2,
    ShieldCheck,
    Trash2,
    Upload,
    UserRound,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ChangeEvent, type FormEvent } from 'react';

import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface ProfileData {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    initials: string;
    photo_url: string | null;
    email_verified_at: string | null;
    created_at: string | null;
}

interface ProfilePageProps {
    profile: ProfileData;
}

interface PhotoForm {
    [key: string]: File | null;
    photo: File | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profil',
        href: '/profile',
    },
];

const allowedPhotoTypes = ['image/jpeg', 'image/png', 'image/webp'];

const maximumPhotoSize = 5 * 1024 * 1024;

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'long',
    }).format(date);
}

export default function ProfileShow({ profile }: ProfilePageProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [isDeletingPhoto, setIsDeletingPhoto] = useState(false);

    const photoForm = useForm<PhotoForm>({
        photo: null,
    });

    const displayedPhotoUrl = useMemo(() => {
        if (!photoForm.data.photo) {
            return profile.photo_url;
        }

        return URL.createObjectURL(photoForm.data.photo);
    }, [photoForm.data.photo, profile.photo_url]);

    useEffect(() => {
        if (!photoForm.data.photo || !displayedPhotoUrl) {
            return;
        }

        return () => {
            URL.revokeObjectURL(displayedPhotoUrl);
        };
    }, [displayedPhotoUrl, photoForm.data.photo]);

    function selectPhoto(event: ChangeEvent<HTMLInputElement>): void {
        const photo = event.target.files?.[0] ?? null;

        photoForm.clearErrors('photo');

        if (!photo) {
            photoForm.setData('photo', null);

            return;
        }

        if (!allowedPhotoTypes.includes(photo.type)) {
            photoForm.setError('photo', 'Foto harus berformat JPG, JPEG, PNG, atau WEBP.');

            event.target.value = '';

            return;
        }

        if (photo.size > maximumPhotoSize) {
            photoForm.setError('photo', 'Ukuran foto maksimal 5 MB.');

            event.target.value = '';

            return;
        }

        photoForm.setData('photo', photo);
    }

    function submitPhoto(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        if (!photoForm.data.photo) {
            photoForm.setError('photo', 'Pilih foto profil terlebih dahulu.');

            return;
        }

        photoForm.post('/profile/photo', {
            forceFormData: true,
            preserveScroll: true,

            onSuccess: () => {
                photoForm.reset('photo');

                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
        });
    }

    function cancelSelectedPhoto(): void {
        photoForm.reset('photo');
        photoForm.clearErrors('photo');

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    }

    function deletePhoto(): void {
        if (!profile.photo_url || isDeletingPhoto) {
            return;
        }

        const confirmed = window.confirm('Hapus foto profil saat ini?');

        if (!confirmed) {
            return;
        }

        cancelSelectedPhoto();
        setIsDeletingPhoto(true);

        router.delete('/profile/photo', {
            preserveScroll: true,

            onFinish: () => {
                setIsDeletingPhoto(false);
            },
        });
    }

    const emailVerified = profile.email_verified_at !== null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profil Admin" />

            <main className="settings-page min-h-full px-4 py-6 md:px-6 md:py-8">
                <div className="relative z-10 mx-auto w-full max-w-[1500px] space-y-6">
                    <section className="settings-hero relative overflow-hidden rounded-[30px] p-6 md:p-8">
                        <div className="relative z-10 flex items-start gap-4">
                            <span className="settings-hero-icon grid size-14 shrink-0 place-items-center rounded-2xl">
                                <UserRound className="size-7" />
                            </span>

                            <div>
                                <p className="text-xs font-extrabold tracking-[0.18em] text-blue-200 uppercase">Identitas Pengguna</p>

                                <h1 className="mt-2 text-3xl font-extrabold tracking-[-0.04em] text-white md:text-4xl">Profil Admin</h1>

                                <p className="mt-3 max-w-2xl text-sm leading-7 text-blue-100/80 md:text-base">
                                    Informasi akun dan identitas pengguna yang sedang aktif di SchoolSafe.
                                </p>
                            </div>
                        </div>
                    </section>

                    <div className="grid items-start gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
                        <aside className="profile-card rounded-[28px] p-5 sm:p-6">
                            <div className="text-center">
                                <div className="relative mx-auto size-44">
                                    <div className="h-full w-full overflow-hidden rounded-[34px] border-4 border-white bg-slate-100 shadow-[0_22px_55px_rgba(15,23,42,0.18)]">
                                        {displayedPhotoUrl ? (
                                            <img src={displayedPhotoUrl} alt={`Foto profil ${profile.name}`} className="h-full w-full object-cover" />
                                        ) : (
                                            <div className="profile-avatar grid h-full w-full place-items-center text-5xl font-extrabold text-white">
                                                {profile.initials}
                                            </div>
                                        )}
                                    </div>

                                    <span className="absolute -right-2 -bottom-2 grid size-12 place-items-center rounded-2xl border-4 border-white bg-blue-600 text-white shadow-lg">
                                        <Camera className="size-5" />
                                    </span>
                                </div>

                                <h2 className="mt-6 text-2xl font-extrabold tracking-[-0.03em] text-slate-950">{profile.name}</h2>

                                <p className="mt-1 text-sm font-medium text-slate-500">{profile.email}</p>

                                <span className="mt-4 inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-extrabold text-blue-700">
                                    <ShieldCheck className="size-4" />
                                    {profile.role_label}
                                </span>
                            </div>

                            <form onSubmit={submitPhoto} className="mt-6 space-y-3 border-t border-slate-200 pt-5">
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={selectPhoto}
                                    className="sr-only"
                                    id="admin-profile-photo"
                                />

                                <label
                                    htmlFor="admin-profile-photo"
                                    className="inline-flex h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 text-sm font-extrabold text-blue-700 transition hover:-translate-y-0.5 hover:border-blue-300 hover:bg-blue-100"
                                >
                                    <Camera className="size-4" />
                                    Pilih Foto
                                </label>

                                {photoForm.data.photo && (
                                    <div className="grid grid-cols-2 gap-2">
                                        <button
                                            type="submit"
                                            disabled={photoForm.processing}
                                            className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 text-sm font-extrabold text-white shadow-lg shadow-blue-200 transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            {photoForm.processing ? <LoaderCircle className="size-4 animate-spin" /> : <Upload className="size-4" />}
                                            Simpan
                                        </button>

                                        <button
                                            type="button"
                                            onClick={cancelSelectedPhoto}
                                            disabled={photoForm.processing}
                                            className="h-11 rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                )}

                                {profile.photo_url && !photoForm.data.photo && (
                                    <button
                                        type="button"
                                        onClick={deletePhoto}
                                        disabled={isDeletingPhoto}
                                        className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 text-xs font-extrabold text-red-600 transition hover:bg-red-100 disabled:opacity-60"
                                    >
                                        {isDeletingPhoto ? <LoaderCircle className="size-4 animate-spin" /> : <Trash2 className="size-4" />}
                                        Hapus Foto
                                    </button>
                                )}

                                <InputError message={photoForm.errors.photo} />

                                {photoForm.recentlySuccessful && (
                                    <p role="status" className="flex items-center justify-center gap-2 text-xs font-bold text-emerald-700">
                                        <CheckCircle2 className="size-4" />
                                        Foto berhasil diperbarui
                                    </p>
                                )}

                                <p className="text-center text-xs leading-5 text-slate-400">JPG, PNG, atau WEBP. Maksimal 5 MB.</p>
                            </form>
                        </aside>

                        <div className="space-y-6">
                            <section className="profile-overview-card relative overflow-hidden rounded-[28px] p-5 sm:p-7">
                                <div className="relative z-10 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="profile-eyebrow text-xs font-extrabold tracking-[0.15em] uppercase">Akun SchoolSafe</p>

                                            <span
                                                className={
                                                    emailVerified ? 'profile-badge profile-badge-success' : 'profile-badge profile-badge-warning'
                                                }
                                            >
                                                {emailVerified ? <BadgeCheck className="size-3.5" /> : <Mail className="size-3.5" />}

                                                {emailVerified ? 'Email terverifikasi' : 'Belum diverifikasi'}
                                            </span>
                                        </div>

                                        <h2 className="mt-3 text-3xl font-extrabold tracking-[-0.04em] text-slate-950">
                                            Selamat datang, {profile.name}
                                        </h2>

                                        <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                                            Profil ini digunakan sebagai identitas resmi ketika mengelola aktivitas SchoolSafe.
                                        </p>
                                    </div>

                                    <div className="profile-account-status rounded-2xl px-4 py-3">
                                        <p className="text-xs font-bold text-slate-400">Status akun</p>

                                        <p className="mt-1 flex items-center gap-2 text-sm font-extrabold text-emerald-700">
                                            <span className="size-2 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.14)]" />
                                            Aktif
                                        </p>
                                    </div>
                                </div>
                            </section>

                            <section className="profile-card rounded-[28px] p-5 sm:p-7">
                                <header className="flex items-start gap-4 border-b border-slate-200 pb-5">
                                    <span className="profile-section-icon grid size-12 shrink-0 place-items-center rounded-2xl">
                                        <UserRound className="size-6" />
                                    </span>

                                    <div>
                                        <h2 className="text-lg font-extrabold text-slate-950">Informasi Admin</h2>

                                        <p className="mt-1 text-sm text-slate-500">Detail identitas akun yang tercatat di sistem.</p>
                                    </div>
                                </header>

                                <div className="mt-6 grid gap-4 md:grid-cols-2">
                                    <InfoCard icon={UserRound} label="Nama lengkap" value={profile.name} />

                                    <InfoCard icon={Mail} label="Alamat email" value={profile.email} />

                                    <InfoCard icon={ShieldCheck} label="Peran pengguna" value={profile.role_label} />

                                    <InfoCard icon={Clock3} label="Bergabung sejak" value={formatDate(profile.created_at)} />
                                </div>
                            </section>

                            <section className="profile-card rounded-[28px] p-5 sm:p-7">
                                <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                                    <div className="flex items-start gap-4">
                                        <span className="profile-security-icon grid size-12 shrink-0 place-items-center rounded-2xl">
                                            <Settings2 className="size-6" />
                                        </span>

                                        <div>
                                            <h2 className="text-lg font-extrabold text-slate-950">Pengaturan Akun</h2>

                                            <p className="mt-1 max-w-xl text-sm leading-6 text-slate-500">
                                                Perubahan nama, email, kata sandi, tampilan, dan penghapusan akun dikelola pada halaman Settings.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex flex-col gap-2 sm:flex-row">
                                        <Link
                                            href="/settings/profile"
                                            className="profile-save-button inline-flex h-11 items-center justify-center gap-2 rounded-xl px-5 text-sm font-extrabold"
                                        >
                                            <Settings2 className="size-4" />
                                            Buka Settings
                                        </Link>

                                        <Link
                                            href="/settings/password"
                                            className="profile-security-link inline-flex h-11 items-center justify-center gap-2 rounded-xl px-5 text-sm font-extrabold"
                                        >
                                            <KeyRound className="size-4" />
                                            Kata Sandi
                                        </Link>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </main>
        </AppLayout>
    );
}

interface InfoCardProps {
    icon: typeof UserRound;
    label: string;
    value: string;
}

function InfoCard({ icon: Icon, label, value }: InfoCardProps) {
    return (
        <article className="profile-security-row flex items-start gap-3 rounded-2xl p-4">
            <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600">
                <Icon className="size-5" />
            </span>

            <div className="min-w-0">
                <p className="text-xs font-bold tracking-wide text-slate-400 uppercase">{label}</p>

                <p className="mt-1 text-sm font-extrabold break-words text-slate-800">{value}</p>
            </div>
        </article>
    );
}
