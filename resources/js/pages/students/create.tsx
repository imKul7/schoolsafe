import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, BookOpen, CalendarDays, GraduationCap, Hash, Save, UserRound } from 'lucide-react';
import { type FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';

type Gender = '' | 'male' | 'female';

type StudentStatus = 'active' | 'inactive' | 'graduated';

interface SchoolClass {
    id: number;
    name: string;
    grade_level: number;
    academic_year: string;
    homeroom_teacher: string | null;
}

interface CreateStudentProps {
    classes?: SchoolClass[];
}

interface StudentForm {
    [key: string]: string;

    school_class_id: string;
    student_number: string;
    nisn: string;
    full_name: string;
    gender: Gender;
    date_of_birth: string;
    status: StudentStatus;
    notes: string;
}

function inputClass(hasError: boolean, hasIcon = false): string {
    return [
        'h-12 w-full rounded-xl border bg-[#fbfdff] px-4',
        'text-sm text-[#334e68] placeholder:text-[#bcccdc]',
        'outline-none transition',
        'focus:ring-2 focus:ring-[#dcebf8]',
        'disabled:cursor-not-allowed disabled:bg-[#f5f8fb]',
        'disabled:opacity-70',
        hasIcon ? 'pl-11' : '',
        hasError ? 'border-[#e97a7a] focus:border-[#e97a7a]' : 'border-[#d9e5ee] focus:border-[#7fa9d8]',
    ]
        .filter(Boolean)
        .join(' ');
}

function textareaClass(hasError: boolean): string {
    return [
        'w-full resize-none rounded-xl border bg-[#fbfdff] px-4 py-3',
        'text-sm leading-6 text-[#334e68] placeholder:text-[#bcccdc]',
        'outline-none transition focus:ring-2 focus:ring-[#dcebf8]',
        'disabled:cursor-not-allowed disabled:bg-[#f5f8fb]',
        'disabled:opacity-70',
        hasError ? 'border-[#e97a7a] focus:border-[#e97a7a]' : 'border-[#d9e5ee] focus:border-[#7fa9d8]',
    ].join(' ');
}

function getLocalDate(): string {
    const date = new Date();

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export default function CreateStudent({ classes = [] }: CreateStudentProps) {
    const { data, setData, post, processing, errors, clearErrors } = useForm<StudentForm>({
        school_class_id: '',
        student_number: '',
        nisn: '',
        full_name: '',
        gender: '',
        date_of_birth: '',
        status: 'active',
        notes: '',
    });

    const selectedClass = classes.find((schoolClass) => String(schoolClass.id) === data.school_class_id);

    const submit: FormEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();

        clearErrors();

        post('/students', {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout>
            <Head title="Tambah Siswa" />

            <main className="min-h-full bg-[#f8fafc]">
                <div className="mx-auto w-full max-w-5xl p-4 md:p-6">
                    <Link
                        href="/students"
                        className="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[#627d98] transition hover:text-[#4f7cac]"
                    >
                        <ArrowLeft aria-hidden="true" className="size-4" />
                        Kembali ke Data Siswa
                    </Link>

                    <section className="relative mb-6 overflow-hidden rounded-[28px] border border-[#deebf5] bg-gradient-to-r from-[#edf6ff] via-[#f2faf8] to-[#fffaf0] p-6 shadow-sm md:p-8">
                        <div className="absolute -top-20 -right-16 size-52 rounded-full bg-white/50 blur-3xl" />

                        <div className="relative flex items-start gap-4">
                            <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white text-[#5b8def] shadow-sm">
                                <UserRound aria-hidden="true" className="size-6" />
                            </div>

                            <div>
                                <h1 className="text-2xl font-bold text-[#243b53] md:text-3xl">Tambah Siswa</h1>

                                <p className="mt-2 max-w-2xl text-sm leading-6 text-[#627d98]">
                                    Lengkapi identitas dan informasi akademik siswa yang akan didaftarkan ke SchoolSafe.
                                </p>
                            </div>
                        </div>
                    </section>

                    {classes.length === 0 && (
                        <section role="alert" className="mb-6 rounded-2xl border border-[#f1dfae] bg-[#fff8df] p-5">
                            <div className="flex items-start gap-3">
                                <AlertCircle className="mt-0.5 size-5 shrink-0 text-[#b88a22]" />

                                <div>
                                    <h2 className="font-semibold text-[#7d5d18]">Belum ada kelas aktif</h2>

                                    <p className="mt-1 text-sm leading-6 text-[#99752b]">
                                        Data siswa belum dapat disimpan karena sekolah belum memiliki kelas aktif.
                                    </p>
                                </div>
                            </div>
                        </section>
                    )}

                    <form onSubmit={submit} className="space-y-6" aria-busy={processing} noValidate>
                        <section className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-7">
                            <div className="mb-6 flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-xl bg-[#eef6ff] text-[#5b8def]">
                                    <UserRound className="size-5" />
                                </div>

                                <div>
                                    <h2 className="font-bold text-[#243b53]">Identitas Siswa</h2>

                                    <p className="text-sm text-[#829ab1]">Informasi dasar dan identitas resmi siswa.</p>
                                </div>
                            </div>

                            <div className="grid gap-5 md:grid-cols-2">
                                <div className="md:col-span-2">
                                    <label htmlFor="full_name" className="mb-2 block text-sm font-semibold text-[#334e68]">
                                        Nama lengkap
                                        <span className="ml-1 text-[#e97a7a]">*</span>
                                    </label>

                                    <input
                                        id="full_name"
                                        name="full_name"
                                        type="text"
                                        value={data.full_name}
                                        onChange={(event) => {
                                            setData('full_name', event.currentTarget.value);

                                            clearErrors('full_name');
                                        }}
                                        disabled={processing}
                                        placeholder="Contoh: Siswa Uji CRUD"
                                        autoComplete="name"
                                        className={inputClass(Boolean(errors.full_name))}
                                    />

                                    <InputError message={errors.full_name} className="mt-2" />
                                </div>

                                <div>
                                    <label htmlFor="student_number" className="mb-2 block text-sm font-semibold text-[#334e68]">
                                        Nomor siswa
                                        <span className="ml-1 text-[#e97a7a]">*</span>
                                    </label>

                                    <div className="relative">
                                        <Hash className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#9fb3c8]" />

                                        <input
                                            id="student_number"
                                            name="student_number"
                                            type="text"
                                            value={data.student_number}
                                            onChange={(event) => {
                                                setData('student_number', event.currentTarget.value);

                                                clearErrors('student_number');
                                            }}
                                            disabled={processing}
                                            placeholder="Contoh: SS-TEST-01"
                                            autoComplete="off"
                                            className={inputClass(Boolean(errors.student_number), true)}
                                        />
                                    </div>

                                    <InputError message={errors.student_number} className="mt-2" />
                                </div>

                                <div>
                                    <label htmlFor="nisn" className="mb-2 block text-sm font-semibold text-[#334e68]">
                                        NISN
                                    </label>

                                    <input
                                        id="nisn"
                                        name="nisn"
                                        type="text"
                                        inputMode="numeric"
                                        maxLength={10}
                                        value={data.nisn}
                                        onChange={(event) => {
                                            const value = event.currentTarget.value.replace(/\D/g, '').slice(0, 10);

                                            setData('nisn', value);
                                            clearErrors('nisn');
                                        }}
                                        disabled={processing}
                                        placeholder="Masukkan 10 digit NISN"
                                        autoComplete="off"
                                        className={inputClass(Boolean(errors.nisn))}
                                    />

                                    <div className="mt-1 flex items-start justify-between gap-3">
                                        <InputError message={errors.nisn} />

                                        <span className="ml-auto text-xs text-[#9fb3c8]">{data.nisn.length}/10</span>
                                    </div>
                                </div>

                                <div>
                                    <label htmlFor="gender" className="mb-2 block text-sm font-semibold text-[#334e68]">
                                        Jenis kelamin
                                        <span className="ml-1 text-[#e97a7a]">*</span>
                                    </label>

                                    <select
                                        id="gender"
                                        name="gender"
                                        value={data.gender}
                                        onChange={(event) => {
                                            setData('gender', event.currentTarget.value as Gender);

                                            clearErrors('gender');
                                        }}
                                        disabled={processing}
                                        className={inputClass(Boolean(errors.gender))}
                                    >
                                        <option value="">Pilih jenis kelamin</option>

                                        <option value="male">Laki-laki</option>

                                        <option value="female">Perempuan</option>
                                    </select>

                                    <InputError message={errors.gender} className="mt-2" />
                                </div>

                                <div>
                                    <label htmlFor="date_of_birth" className="mb-2 block text-sm font-semibold text-[#334e68]">
                                        Tanggal lahir
                                    </label>

                                    <div className="relative">
                                        <CalendarDays className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#9fb3c8]" />

                                        <input
                                            id="date_of_birth"
                                            name="date_of_birth"
                                            type="date"
                                            max={getLocalDate()}
                                            value={data.date_of_birth}
                                            onChange={(event) => {
                                                setData('date_of_birth', event.currentTarget.value);

                                                clearErrors('date_of_birth');
                                            }}
                                            disabled={processing}
                                            className={inputClass(Boolean(errors.date_of_birth), true)}
                                        />
                                    </div>

                                    <InputError message={errors.date_of_birth} className="mt-2" />
                                </div>
                            </div>
                        </section>

                        <section className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-7">
                            <div className="mb-6 flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-xl bg-[#eef9f6] text-[#4c9e94]">
                                    <GraduationCap className="size-5" />
                                </div>

                                <div>
                                    <h2 className="font-bold text-[#243b53]">Informasi Akademik</h2>

                                    <p className="text-sm text-[#829ab1]">Kelas dan status siswa.</p>
                                </div>
                            </div>

                            <div className="grid gap-5 md:grid-cols-2">
                                <div>
                                    <label htmlFor="school_class_id" className="mb-2 block text-sm font-semibold text-[#334e68]">
                                        Kelas
                                        <span className="ml-1 text-[#e97a7a]">*</span>
                                    </label>

                                    <select
                                        id="school_class_id"
                                        name="school_class_id"
                                        value={data.school_class_id}
                                        onChange={(event) => {
                                            setData('school_class_id', event.currentTarget.value);

                                            clearErrors('school_class_id');
                                        }}
                                        disabled={processing || classes.length === 0}
                                        className={inputClass(Boolean(errors.school_class_id))}
                                    >
                                        <option value="">Pilih kelas siswa</option>

                                        {classes.map((schoolClass) => (
                                            <option key={schoolClass.id} value={schoolClass.id}>
                                                Kelas {schoolClass.name} · {schoolClass.academic_year}
                                            </option>
                                        ))}
                                    </select>

                                    <InputError message={errors.school_class_id} className="mt-2" />

                                    {selectedClass && (
                                        <div className="mt-3 rounded-xl border border-[#dceaf5] bg-[#f6faff] p-3">
                                            <div className="flex items-start gap-3">
                                                <BookOpen className="mt-0.5 size-4 shrink-0 text-[#5b8def]" />

                                                <div className="text-xs leading-5 text-[#627d98]">
                                                    <p>
                                                        Tingkat: <strong className="text-[#334e68]">{selectedClass.grade_level}</strong>
                                                    </p>

                                                    <p>
                                                        Wali kelas:{' '}
                                                        <strong className="text-[#334e68]">
                                                            {selectedClass.homeroom_teacher ?? 'Belum ditentukan'}
                                                        </strong>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                <div>
                                    <label htmlFor="status" className="mb-2 block text-sm font-semibold text-[#334e68]">
                                        Status siswa
                                        <span className="ml-1 text-[#e97a7a]">*</span>
                                    </label>

                                    <select
                                        id="status"
                                        name="status"
                                        value={data.status}
                                        onChange={(event) => {
                                            setData('status', event.currentTarget.value as StudentStatus);

                                            clearErrors('status');
                                        }}
                                        disabled={processing}
                                        className={inputClass(Boolean(errors.status))}
                                    >
                                        <option value="active">Aktif</option>

                                        <option value="inactive">Tidak aktif</option>

                                        <option value="graduated">Lulus</option>
                                    </select>

                                    <InputError message={errors.status} className="mt-2" />
                                </div>

                                <div className="md:col-span-2">
                                    <label htmlFor="notes" className="mb-2 block text-sm font-semibold text-[#334e68]">
                                        Catatan
                                    </label>

                                    <textarea
                                        id="notes"
                                        name="notes"
                                        rows={5}
                                        maxLength={2000}
                                        value={data.notes}
                                        onChange={(event) => {
                                            setData('notes', event.currentTarget.value);

                                            clearErrors('notes');
                                        }}
                                        disabled={processing}
                                        placeholder="Tambahkan catatan khusus mengenai siswa..."
                                        className={textareaClass(Boolean(errors.notes))}
                                    />

                                    <div className="mt-1 flex items-start justify-between gap-4">
                                        <InputError message={errors.notes} />

                                        <span className="ml-auto text-xs text-[#9fb3c8]">{data.notes.length}/2000</span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section className="flex flex-col-reverse gap-3 rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm sm:flex-row sm:justify-end">
                            <Link
                                href="/students"
                                className="inline-flex h-11 items-center justify-center rounded-xl border border-[#d9e5ee] px-5 text-sm font-semibold text-[#627d98] transition hover:bg-[#f7fafc]"
                            >
                                Batal
                            </Link>

                            <button
                                type="submit"
                                disabled={processing || classes.length === 0}
                                className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#5b8def] px-6 text-sm font-semibold text-white shadow-md shadow-blue-200/60 transition hover:bg-[#4c7fd9] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing ? (
                                    <>
                                        <span className="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" />
                                        Menyimpan...
                                    </>
                                ) : (
                                    <>
                                        <Save className="size-4" />
                                        Simpan Siswa
                                    </>
                                )}
                            </button>
                        </section>
                    </form>
                </div>
            </main>
        </AppLayout>
    );
}
