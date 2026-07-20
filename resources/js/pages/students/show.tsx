import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpen,
    CalendarDays,
    GraduationCap,
    Hash,
    Pencil,
    Power,
    ShieldCheck,
    Trash2,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/app-layout';

interface SchoolClass {
    id: number;
    name: string;
    grade_level: number;
    academic_year: string;
    homeroom_teacher: string | null;
}

interface Student {
    id: number;
    student_number: string;
    nisn: string | null;
    full_name: string;
    gender: 'male' | 'female';
    date_of_birth: string | null;
    status: 'active' | 'inactive' | 'graduated';
    notes: string | null;
    photo_path: string | null;
    initials: string;
    class: SchoolClass;
}

interface StudentShowProps {
    student: Student;
}

const statusStyles: Record<Student['status'], string> = {
    active: 'bg-[#e8f6f3] text-[#438f86]',
    inactive: 'bg-[#f1f5f9] text-[#627d98]',
    graduated: 'bg-[#eef3ff] text-[#5b73b8]',
};

const statusLabels: Record<Student['status'], string> = {
    active: 'Aktif',
    inactive: 'Tidak aktif',
    graduated: 'Lulus',
};

export default function StudentShow({
    student,
}: StudentShowProps) {
    const [processingStatus, setProcessingStatus] =
        useState(false);

    const [processingDelete, setProcessingDelete] =
        useState(false);

    const toggleStatus = () => {
        const action =
            student.status === 'active'
                ? 'menonaktifkan'
                : 'mengaktifkan';

        const confirmed = window.confirm(
            `Apakah Anda yakin ingin ${action} ${student.full_name}?`,
        );

        if (!confirmed) {
            return;
        }

        router.patch(
            `/students/${student.id}/toggle-status`,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessingStatus(true),
                onFinish: () => setProcessingStatus(false),
            },
        );
    };

    const deleteStudent = () => {
        const confirmed = window.confirm(
            `Pindahkan data ${student.full_name} ke arsip?`,
        );

        if (!confirmed) {
            return;
        }

        router.delete(`/students/${student.id}`, {
            onStart: () => setProcessingDelete(true),
            onFinish: () => setProcessingDelete(false),
        });
    };

    return (
        <AppLayout>
            <Head title={student.full_name} />

            <main className="min-h-full bg-[#f8fafc]">
                <div className="mx-auto w-full max-w-6xl p-4 md:p-6">
                    <Link
                        href="/students"
                        className="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[#627d98] transition hover:text-[#4f7cac]"
                    >
                        <ArrowLeft className="size-4" />
                        Kembali ke Data Siswa
                    </Link>

                    <section className="overflow-hidden rounded-[28px] border border-[#deebf5] bg-gradient-to-r from-[#edf6ff] via-[#f2faf8] to-[#fffaf0] p-6 shadow-sm md:p-8">
                        <div className="flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                            <div className="flex items-center gap-4">
                                <div className="flex size-20 shrink-0 items-center justify-center rounded-[24px] bg-white text-2xl font-bold text-[#4f7cac] shadow-sm">
                                    {student.initials}
                                </div>

                                <div>
                                    <span
                                        className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${statusStyles[student.status]}`}
                                    >
                                        {statusLabels[student.status]}
                                    </span>

                                    <h1 className="mt-3 text-2xl font-bold text-[#243b53] md:text-3xl">
                                        {student.full_name}
                                    </h1>

                                    <p className="mt-1 text-sm text-[#627d98]">
                                        Kelas {student.class.name} ·{' '}
                                        {student.student_number}
                                    </p>
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Link
                                    href={`/students/${student.id}/edit`}
                                    className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#5b8def] px-4 text-sm font-semibold text-white shadow-md transition hover:bg-[#4c7fd9]"
                                >
                                    <Pencil className="size-4" />
                                    Edit
                                </Link>

                                {student.status !== 'graduated' && (
                                    <button
                                        type="button"
                                        onClick={toggleStatus}
                                        disabled={processingStatus}
                                        className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-[#d9e5ee] bg-white px-4 text-sm font-semibold text-[#627d98] transition hover:bg-[#f7fafc] disabled:opacity-60"
                                    >
                                        <Power className="size-4" />

                                        {processingStatus
                                            ? 'Memproses...'
                                            : student.status === 'active'
                                              ? 'Nonaktifkan'
                                              : 'Aktifkan'}
                                    </button>
                                )}

                                <button
                                    type="button"
                                    onClick={deleteStudent}
                                    disabled={processingDelete}
                                    className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-[#f0d0d0] bg-white px-4 text-sm font-semibold text-[#cf6464] transition hover:bg-[#fff2f2] disabled:opacity-60"
                                >
                                    <Trash2 className="size-4" />

                                    {processingDelete
                                        ? 'Menghapus...'
                                        : 'Arsipkan'}
                                </button>
                            </div>
                        </div>
                    </section>

                    <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_0.7fr]">
                        <section className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-7">
                            <div className="mb-6 flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-xl bg-[#eef6ff] text-[#5b8def]">
                                    <UserRound className="size-5" />
                                </div>

                                <div>
                                    <h2 className="font-bold text-[#243b53]">
                                        Identitas Siswa
                                    </h2>

                                    <p className="text-sm text-[#829ab1]">
                                        Informasi pribadi siswa.
                                    </p>
                                </div>
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <InfoItem
                                    icon={Hash}
                                    label="Nomor Siswa"
                                    value={student.student_number}
                                />

                                <InfoItem
                                    icon={ShieldCheck}
                                    label="NISN"
                                    value={student.nisn ?? '-'}
                                />

                                <InfoItem
                                    icon={UserRound}
                                    label="Jenis Kelamin"
                                    value={
                                        student.gender === 'male'
                                            ? 'Laki-laki'
                                            : 'Perempuan'
                                    }
                                />

                                <InfoItem
                                    icon={CalendarDays}
                                    label="Tanggal Lahir"
                                    value={
                                        student.date_of_birth ?? '-'
                                    }
                                />
                            </div>
                        </section>

                        <section className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-7">
                            <div className="mb-6 flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-xl bg-[#eef9f6] text-[#4c9e94]">
                                    <GraduationCap className="size-5" />
                                </div>

                                <div>
                                    <h2 className="font-bold text-[#243b53]">
                                        Informasi Akademik
                                    </h2>

                                    <p className="text-sm text-[#829ab1]">
                                        Kelas dan tahun ajaran.
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-5">
                                <InfoItem
                                    icon={BookOpen}
                                    label="Kelas"
                                    value={`Kelas ${student.class.name}`}
                                />

                                <InfoItem
                                    icon={CalendarDays}
                                    label="Tahun Ajaran"
                                    value={student.class.academic_year}
                                />

                                <InfoItem
                                    icon={UserRound}
                                    label="Wali Kelas"
                                    value={
                                        student.class
                                            .homeroom_teacher ?? '-'
                                    }
                                />
                            </div>
                        </section>
                    </div>

                    <section className="mt-6 rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-7">
                        <h2 className="font-bold text-[#243b53]">
                            Catatan Siswa
                        </h2>

                        <p className="mt-3 whitespace-pre-wrap text-sm leading-7 text-[#627d98]">
                            {student.notes ||
                                'Belum ada catatan khusus untuk siswa ini.'}
                        </p>
                    </section>
                </div>
            </main>
        </AppLayout>
    );
}

interface InfoItemProps {
    icon: typeof UserRound;
    label: string;
    value: string;
}

function InfoItem({
    icon: Icon,
    label,
    value,
}: InfoItemProps) {
    return (
        <div className="flex items-start gap-3">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#f5f8fb] text-[#829ab1]">
                <Icon className="size-4" />
            </div>

            <div>
                <p className="text-xs font-medium uppercase tracking-wide text-[#9fb3c8]">
                    {label}
                </p>

                <p className="mt-1 text-sm font-semibold text-[#334e68]">
                    {value}
                </p>
            </div>
        </div>
    );
}