import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    BookOpen,
    CalendarDays,
    GraduationCap,
    Hash,
    Save,
    UserRound,
} from 'lucide-react';
import {
    type FormEventHandler,
    type MouseEvent as ReactMouseEvent,
    type ReactNode,
} from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';

type Gender = 'male' | 'female';

type StudentStatus = 'active' | 'inactive' | 'graduated';

type StudentField =
    | 'school_class_id'
    | 'student_number'
    | 'nisn'
    | 'full_name'
    | 'gender'
    | 'date_of_birth'
    | 'status'
    | 'notes';

interface SchoolClass {
    id: number;
    name: string;
    grade_level: number;
    academic_year: string;
    homeroom_teacher: string | null;
}

interface Student {
    id: number;
    school_class_id: string;
    student_number: string;
    nisn: string;
    full_name: string;
    gender: Gender;
    date_of_birth: string;
    status: StudentStatus;
    notes: string;
}

interface EditStudentProps {
    student: Student;
    classes: SchoolClass[];
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

interface FormLabelProps {
    htmlFor: string;
    children: ReactNode;
    required?: boolean;
    description?: string;
}

interface SectionHeaderProps {
    icon: typeof UserRound;
    title: string;
    description: string;
    tone?: 'blue' | 'green';
}

const statusDescriptions: Record<StudentStatus, string> = {
    active: 'Siswa masih aktif mengikuti kegiatan sekolah.',
    inactive: 'Siswa sementara tidak aktif dalam sistem.',
    graduated: 'Siswa telah menyelesaikan pendidikan.',
};

function inputClass(hasError: boolean, hasIcon = false): string {
    return [
        'h-12 w-full rounded-xl border bg-[#fbfdff] px-4',
        'text-sm text-[#334e68] placeholder:text-[#bcccdc]',
        'outline-none transition duration-200',
        'focus:ring-2 focus:ring-[#dcebf8]',
        'disabled:cursor-not-allowed disabled:bg-[#f5f8fb]',
        'disabled:text-[#829ab1] disabled:opacity-75',
        hasIcon ? 'pl-11' : '',
        hasError
            ? 'border-[#e97a7a] focus:border-[#e97a7a]'
            : 'border-[#d9e5ee] focus:border-[#7fa9d8]',
    ]
        .filter(Boolean)
        .join(' ');
}

function textareaClass(hasError: boolean): string {
    return [
        'w-full resize-none rounded-xl border bg-[#fbfdff] px-4 py-3',
        'text-sm leading-6 text-[#334e68] placeholder:text-[#bcccdc]',
        'outline-none transition duration-200',
        'focus:ring-2 focus:ring-[#dcebf8]',
        'disabled:cursor-not-allowed disabled:bg-[#f5f8fb]',
        'disabled:text-[#829ab1] disabled:opacity-75',
        hasError
            ? 'border-[#e97a7a] focus:border-[#e97a7a]'
            : 'border-[#d9e5ee] focus:border-[#7fa9d8]',
    ].join(' ');
}

export default function EditStudent({
    student,
    classes,
}: EditStudentProps) {
    const {
        data,
        setData,
        put,
        processing,
        errors,
        clearErrors,
        isDirty,
    } = useForm<StudentForm>({
        school_class_id: student.school_class_id,
        student_number: student.student_number,
        nisn: student.nisn,
        full_name: student.full_name,
        gender: student.gender,
        date_of_birth: student.date_of_birth,
        status: student.status,
        notes: student.notes,
    });

    const today = new Date().toISOString().slice(0, 10);

    const selectedClass = classes.find(
        (schoolClass) =>
            String(schoolClass.id) === data.school_class_id,
    );

    const errorMessages = Object.values(errors).filter(
        (message): message is string => Boolean(message),
    );

    const updateField = (
        field: StudentField,
        value: string,
    ): void => {
        setData(field, value);

        if (errors[field]) {
            clearErrors(field);
        }
    };

    const submit: FormEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();

        clearErrors();

        put(`/students/${student.id}`, {
            preserveScroll: true,
        });
    };

    const confirmLeave = (
        event: ReactMouseEvent<Element>,
    ): void => {
        if (!isDirty) {
            return;
        }

        const confirmed = window.confirm(
            'Perubahan yang belum disimpan akan hilang. Tetap tinggalkan halaman?',
        );

        if (!confirmed) {
            event.preventDefault();
        }
    };

    return (
        <AppLayout>
            <Head title={`Edit ${student.full_name}`} />

            <main className="min-h-full bg-[#f8fafc]">
                <div className="mx-auto w-full max-w-5xl p-4 md:p-6">
                    <Link
                        href={`/students/${student.id}`}
                        onClick={confirmLeave}
                        className="mb-5 inline-flex items-center gap-2 rounded-lg text-sm font-semibold text-[#627d98] transition hover:text-[#4f7cac] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#dcebf8]"
                    >
                        <ArrowLeft
                            aria-hidden="true"
                            className="size-4"
                        />

                        Kembali ke Detail Siswa
                    </Link>

                    <section className="relative mb-6 overflow-hidden rounded-[26px] border border-[#deebf5] bg-gradient-to-r from-[#edf6ff] via-[#f2faf8] to-[#fffaf0] p-6 shadow-sm md:p-8">
                        <div className="absolute -top-20 -right-16 size-52 rounded-full bg-white/50 blur-3xl" />

                        <div className="relative flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
                            <div className="flex items-center gap-4">
                                <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white text-[#5b8def] shadow-sm">
                                    <UserRound
                                        aria-hidden="true"
                                        className="size-6"
                                    />
                                </div>

                                <div>
                                    <h1 className="text-2xl font-bold text-[#243b53]">
                                        Edit Data Siswa
                                    </h1>

                                    <p className="mt-1 text-sm leading-6 text-[#627d98]">
                                        Perbarui identitas dan informasi
                                        akademik {student.full_name}.
                                    </p>
                                </div>
                            </div>

                            {isDirty && (
                                <span className="inline-flex w-fit items-center gap-2 rounded-full border border-[#f1dfae] bg-[#fff8df] px-3 py-1.5 text-xs font-semibold text-[#9a741f]">
                                    <AlertCircle
                                        aria-hidden="true"
                                        className="size-3.5"
                                    />

                                    Ada perubahan belum disimpan
                                </span>
                            )}
                        </div>
                    </section>

                    {errorMessages.length > 0 && (
                        <section
                            role="alert"
                            aria-live="assertive"
                            className="mb-6 rounded-2xl border border-[#f0cece] bg-[#fff4f4] p-4"
                        >
                            <div className="flex items-start gap-3">
                                <AlertCircle
                                    aria-hidden="true"
                                    className="mt-0.5 size-5 shrink-0 text-[#cf6464]"
                                />

                                <div>
                                    <h2 className="text-sm font-semibold text-[#a64f4f]">
                                        Data belum dapat disimpan
                                    </h2>

                                    <p className="mt-1 text-sm leading-6 text-[#b46363]">
                                        Periksa kembali kolom yang ditandai
                                        merah.
                                    </p>
                                </div>
                            </div>
                        </section>
                    )}

                    <form
                        onSubmit={submit}
                        className="space-y-6"
                        aria-busy={processing}
                        noValidate
                    >
                        <section className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-7">
                            <SectionHeader
                                icon={UserRound}
                                title="Identitas Siswa"
                                description="Informasi dasar dan identitas resmi siswa."
                            />

                            <div className="grid gap-5 md:grid-cols-2">
                                <div className="md:col-span-2">
                                    <FormLabel
                                        htmlFor="full_name"
                                        required
                                    >
                                        Nama lengkap
                                    </FormLabel>

                                    <input
                                        id="full_name"
                                        name="full_name"
                                        type="text"
                                        value={data.full_name}
                                        onChange={(event) =>
                                            updateField(
                                                'full_name',
                                                event.currentTarget.value,
                                            )
                                        }
                                        disabled={processing}
                                        autoComplete="name"
                                        placeholder="Masukkan nama lengkap siswa"
                                        aria-invalid={Boolean(
                                            errors.full_name,
                                        )}
                                        aria-describedby={
                                            errors.full_name
                                                ? 'full-name-error'
                                                : undefined
                                        }
                                        className={inputClass(
                                            Boolean(errors.full_name),
                                        )}
                                    />

                                    <div id="full-name-error">
                                        <InputError
                                            message={errors.full_name}
                                            className="mt-2"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <FormLabel
                                        htmlFor="student_number"
                                        required
                                        description="Nomor siswa harus unik dalam sekolah."
                                    >
                                        Nomor siswa
                                    </FormLabel>

                                    <div className="relative">
                                        <Hash className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#9fb3c8]" />

                                        <input
                                            id="student_number"
                                            name="student_number"
                                            type="text"
                                            value={data.student_number}
                                            onChange={(event) =>
                                                updateField(
                                                    'student_number',
                                                    event.currentTarget.value,
                                                )
                                            }
                                            disabled={processing}
                                            autoComplete="off"
                                            placeholder="Contoh: SS-0001"
                                            aria-invalid={Boolean(
                                                errors.student_number,
                                            )}
                                            className={inputClass(
                                                Boolean(
                                                    errors.student_number,
                                                ),
                                                true,
                                            )}
                                        />
                                    </div>

                                    <InputError
                                        message={errors.student_number}
                                        className="mt-2"
                                    />
                                </div>

                                <div>
                                    <FormLabel
                                        htmlFor="nisn"
                                        description="Isi tepat 10 angka bila tersedia."
                                    >
                                        NISN
                                    </FormLabel>

                                    <input
                                        id="nisn"
                                        name="nisn"
                                        type="text"
                                        inputMode="numeric"
                                        maxLength={10}
                                        value={data.nisn}
                                        onChange={(event) => {
                                            const value =
                                                event.currentTarget.value
                                                    .replace(/\D/g, '')
                                                    .slice(0, 10);

                                            updateField('nisn', value);
                                        }}
                                        disabled={processing}
                                        autoComplete="off"
                                        placeholder="10 digit NISN"
                                        aria-invalid={Boolean(errors.nisn)}
                                        className={inputClass(
                                            Boolean(errors.nisn),
                                        )}
                                    />

                                    <div className="mt-1 flex items-start justify-between gap-3">
                                        <InputError
                                            message={errors.nisn}
                                        />

                                        <span className="ml-auto shrink-0 text-xs text-[#9fb3c8]">
                                            {data.nisn.length}/10
                                        </span>
                                    </div>
                                </div>

                                <div>
                                    <FormLabel
                                        htmlFor="gender"
                                        required
                                    >
                                        Jenis kelamin
                                    </FormLabel>

                                    <select
                                        id="gender"
                                        name="gender"
                                        value={data.gender}
                                        onChange={(event) =>
                                            updateField(
                                                'gender',
                                                event.currentTarget.value,
                                            )
                                        }
                                        disabled={processing}
                                        aria-invalid={Boolean(
                                            errors.gender,
                                        )}
                                        className={inputClass(
                                            Boolean(errors.gender),
                                        )}
                                    >
                                        <option value="male">
                                            Laki-laki
                                        </option>

                                        <option value="female">
                                            Perempuan
                                        </option>
                                    </select>

                                    <InputError
                                        message={errors.gender}
                                        className="mt-2"
                                    />
                                </div>

                                <div>
                                    <FormLabel htmlFor="date_of_birth">
                                        Tanggal lahir
                                    </FormLabel>

                                    <div className="relative">
                                        <CalendarDays className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#9fb3c8]" />

                                        <input
                                            id="date_of_birth"
                                            name="date_of_birth"
                                            type="date"
                                            max={today}
                                            value={data.date_of_birth}
                                            onChange={(event) =>
                                                updateField(
                                                    'date_of_birth',
                                                    event.currentTarget.value,
                                                )
                                            }
                                            disabled={processing}
                                            aria-invalid={Boolean(
                                                errors.date_of_birth,
                                            )}
                                            className={inputClass(
                                                Boolean(
                                                    errors.date_of_birth,
                                                ),
                                                true,
                                            )}
                                        />
                                    </div>

                                    <InputError
                                        message={errors.date_of_birth}
                                        className="mt-2"
                                    />
                                </div>
                            </div>
                        </section>

                        <section className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-7">
                            <SectionHeader
                                icon={GraduationCap}
                                title="Informasi Akademik"
                                description="Kelas, tahun ajaran, dan status siswa."
                                tone="green"
                            />

                            <div className="grid gap-5 md:grid-cols-2">
                                <div>
                                    <FormLabel
                                        htmlFor="school_class_id"
                                        required
                                    >
                                        Kelas
                                    </FormLabel>

                                    <select
                                        id="school_class_id"
                                        name="school_class_id"
                                        value={data.school_class_id}
                                        onChange={(event) =>
                                            updateField(
                                                'school_class_id',
                                                event.currentTarget.value,
                                            )
                                        }
                                        disabled={
                                            processing ||
                                            classes.length === 0
                                        }
                                        aria-invalid={Boolean(
                                            errors.school_class_id,
                                        )}
                                        className={inputClass(
                                            Boolean(
                                                errors.school_class_id,
                                            ),
                                        )}
                                    >
                                        {classes.length === 0 && (
                                            <option value="">
                                                Tidak ada kelas aktif
                                            </option>
                                        )}

                                        {classes.map((schoolClass) => (
                                            <option
                                                key={schoolClass.id}
                                                value={schoolClass.id}
                                            >
                                                Kelas {schoolClass.name} ·{' '}
                                                {
                                                    schoolClass.academic_year
                                                }
                                            </option>
                                        ))}
                                    </select>

                                    <InputError
                                        message={
                                            errors.school_class_id
                                        }
                                        className="mt-2"
                                    />

                                    {selectedClass && (
                                        <div className="mt-3 rounded-xl border border-[#dceaf5] bg-[#f6faff] p-3">
                                            <div className="flex items-start gap-3">
                                                <BookOpen className="mt-0.5 size-4 shrink-0 text-[#5b8def]" />

                                                <div className="text-xs leading-5 text-[#627d98]">
                                                    <p>
                                                        Tingkat:{' '}
                                                        <strong className="text-[#334e68]">
                                                            {
                                                                selectedClass.grade_level
                                                            }
                                                        </strong>
                                                    </p>

                                                    <p>
                                                        Wali kelas:{' '}
                                                        <strong className="text-[#334e68]">
                                                            {selectedClass.homeroom_teacher ??
                                                                'Belum ditentukan'}
                                                        </strong>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                <div>
                                    <FormLabel
                                        htmlFor="status"
                                        required
                                    >
                                        Status siswa
                                    </FormLabel>

                                    <select
                                        id="status"
                                        name="status"
                                        value={data.status}
                                        onChange={(event) =>
                                            updateField(
                                                'status',
                                                event.currentTarget.value,
                                            )
                                        }
                                        disabled={processing}
                                        aria-invalid={Boolean(
                                            errors.status,
                                        )}
                                        className={inputClass(
                                            Boolean(errors.status),
                                        )}
                                    >
                                        <option value="active">
                                            Aktif
                                        </option>

                                        <option value="inactive">
                                            Tidak aktif
                                        </option>

                                        <option value="graduated">
                                            Lulus
                                        </option>
                                    </select>

                                    <InputError
                                        message={errors.status}
                                        className="mt-2"
                                    />

                                    <p className="mt-3 rounded-xl bg-[#f8fafc] px-3 py-2 text-xs leading-5 text-[#829ab1]">
                                        {
                                            statusDescriptions[
                                                data.status
                                            ]
                                        }
                                    </p>
                                </div>

                                <div className="md:col-span-2">
                                    <FormLabel
                                        htmlFor="notes"
                                        description="Catatan hanya dapat dilihat petugas sekolah."
                                    >
                                        Catatan
                                    </FormLabel>

                                    <textarea
                                        id="notes"
                                        name="notes"
                                        rows={5}
                                        maxLength={2000}
                                        value={data.notes}
                                        onChange={(event) =>
                                            updateField(
                                                'notes',
                                                event.currentTarget.value,
                                            )
                                        }
                                        disabled={processing}
                                        placeholder="Tambahkan catatan khusus mengenai siswa bila diperlukan..."
                                        aria-invalid={Boolean(
                                            errors.notes,
                                        )}
                                        className={textareaClass(
                                            Boolean(errors.notes),
                                        )}
                                    />

                                    <div className="mt-1 flex items-start justify-between gap-4">
                                        <InputError
                                            message={errors.notes}
                                        />

                                        <span className="ml-auto shrink-0 text-xs text-[#9fb3c8]">
                                            {data.notes.length}/2000
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section className="sticky bottom-4 z-10 flex flex-col-reverse gap-3 rounded-2xl border border-[#dfe9f1] bg-white/95 p-4 shadow-[0_15px_45px_rgba(50,80,110,0.12)] backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                            <div className="text-xs text-[#829ab1]">
                                {isDirty
                                    ? 'Terdapat perubahan yang belum disimpan.'
                                    : 'Belum ada perubahan pada data siswa.'}
                            </div>

                            <div className="flex flex-col-reverse gap-3 sm:flex-row">
                                <Link
                                    href={`/students/${student.id}`}
                                    onClick={confirmLeave}
                                    className="inline-flex h-11 items-center justify-center rounded-xl border border-[#d9e5ee] px-5 text-sm font-semibold text-[#627d98] transition hover:bg-[#f7fafc]"
                                >
                                    Batal
                                </Link>

                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        !isDirty ||
                                        classes.length === 0
                                    }
                                    className="h-11 rounded-xl bg-[#5b8def] px-6 font-semibold text-white shadow-md shadow-blue-200/60 transition hover:bg-[#4c7fd9] disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {processing ? (
                                        <span className="flex items-center gap-2">
                                            <span
                                                aria-hidden="true"
                                                className="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                                            />

                                            Menyimpan...
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-2">
                                            <Save
                                                aria-hidden="true"
                                                className="size-4"
                                            />

                                            Simpan Perubahan
                                        </span>
                                    )}
                                </Button>
                            </div>
                        </section>
                    </form>
                </div>
            </main>
        </AppLayout>
    );
}

function FormLabel({
    htmlFor,
    children,
    required = false,
    description,
}: FormLabelProps) {
    return (
        <div className="mb-2">
            <label
                htmlFor={htmlFor}
                className="block text-sm font-semibold text-[#334e68]"
            >
                {children}

                {required && (
                    <span
                        aria-hidden="true"
                        className="ml-1 text-[#e97a7a]"
                    >
                        *
                    </span>
                )}
            </label>

            {description && (
                <p className="mt-1 text-xs leading-5 text-[#9fb3c8]">
                    {description}
                </p>
            )}
        </div>
    );
}

function SectionHeader({
    icon: Icon,
    title,
    description,
    tone = 'blue',
}: SectionHeaderProps) {
    const iconStyle =
        tone === 'green'
            ? 'bg-[#eef9f6] text-[#4c9e94]'
            : 'bg-[#eef6ff] text-[#5b8def]';

    return (
        <div className="mb-6 flex items-center gap-3">
            <div
                className={`flex size-10 shrink-0 items-center justify-center rounded-xl ${iconStyle}`}
            >
                <Icon
                    aria-hidden="true"
                    className="size-5"
                />
            </div>

            <div>
                <h2 className="font-bold text-[#243b53]">
                    {title}
                </h2>

                <p className="text-sm leading-6 text-[#829ab1]">
                    {description}
                </p>
            </div>
        </div>
    );
}