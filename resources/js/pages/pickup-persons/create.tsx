import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    CalendarDays,
    IdCard,
    Mail,
    MapPin,
    Phone,
    Plus,
    Save,
    ScanFace,
    Trash2,
    UserRoundCheck,
    UsersRound,
} from 'lucide-react';
import { type FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';

type FaceStatus =
    | 'not_registered'
    | 'registered'
    | 'needs_update';

type RelationshipType =
    | ''
    | 'father'
    | 'mother'
    | 'sibling'
    | 'relative'
    | 'driver'
    | 'guardian'
    | 'other';

interface StudentOption {
    id: number;
    full_name: string;
    student_number: string;
    status: string;
    class_name: string | null;
    academic_year: string | null;
}

interface StudentLinkForm {
    [key: string]: string | boolean;

    student_id: string;
    relationship_type: RelationshipType;
    is_primary: boolean;
    is_active: boolean;
    valid_from: string;
    valid_until: string;
}

interface PickupPersonForm {
    [key: string]:
        | string
        | boolean
        | StudentLinkForm[];

    full_name: string;
    identity_number: string;
    phone: string;
    email: string;
    address: string;
    face_status: FaceStatus;
    is_active: boolean;
    notes: string;
    students: StudentLinkForm[];
}

interface CreatePickupPersonProps {
    students?: StudentOption[];
}

const relationshipOptions: Array<{
    value: Exclude<RelationshipType, ''>;
    label: string;
}> = [
    { value: 'father', label: 'Ayah' },
    { value: 'mother', label: 'Ibu' },
    { value: 'sibling', label: 'Saudara' },
    { value: 'relative', label: 'Kerabat' },
    { value: 'driver', label: 'Pengemudi' },
    { value: 'guardian', label: 'Wali' },
    { value: 'other', label: 'Lainnya' },
];

const emptyStudentLink = (): StudentLinkForm => ({
    student_id: '',
    relationship_type: '',
    is_primary: false,
    is_active: true,
    valid_from: '',
    valid_until: '',
});

function inputClass(
    hasError: boolean,
    hasIcon = false,
): string {
    return [
        'h-12 w-full rounded-xl border bg-[#fbfdff] px-4',
        'text-sm text-[#334e68] placeholder:text-[#bcccdc]',
        'outline-none transition focus:ring-2 focus:ring-[#dcebf8]',
        'disabled:cursor-not-allowed disabled:bg-[#f5f8fb]',
        'disabled:opacity-70',
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
        'outline-none transition focus:ring-2 focus:ring-[#dcebf8]',
        'disabled:cursor-not-allowed disabled:bg-[#f5f8fb]',
        hasError
            ? 'border-[#e97a7a] focus:border-[#e97a7a]'
            : 'border-[#d9e5ee] focus:border-[#7fa9d8]',
    ].join(' ');
}

export default function CreatePickupPerson({
    students = [],
}: CreatePickupPersonProps) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
        clearErrors,
    } = useForm<PickupPersonForm>({
        full_name: '',
        identity_number: '',
        phone: '',
        email: '',
        address: '',
        face_status: 'not_registered',
        is_active: true,
        notes: '',
        students: [emptyStudentLink()],
    });

    const validationErrors =
        errors as Record<string, string | undefined>;

    const fieldError = (
        field: string,
    ): string | undefined => validationErrors[field];

    const updateStudentLink = <K extends keyof StudentLinkForm>(
        index: number,
        field: K,
        value: StudentLinkForm[K],
    ): void => {
        const updatedStudents = data.students.map(
            (studentLink, studentIndex) =>
                studentIndex === index
                    ? {
                          ...studentLink,
                          [field]: value,
                      }
                    : studentLink,
        );

        setData('students', updatedStudents);

        clearErrors(
            `students.${index}.${String(field)}` as keyof PickupPersonForm,
        );
    };

    const addStudentLink = (): void => {
        setData('students', [
            ...data.students,
            emptyStudentLink(),
        ]);
    };

    const removeStudentLink = (index: number): void => {
        if (data.students.length === 1) {
            setData('students', [emptyStudentLink()]);

            return;
        }

        setData(
            'students',
            data.students.filter(
                (_, studentIndex) => studentIndex !== index,
            ),
        );
    };

    const submit: FormEventHandler<HTMLFormElement> = (
        event,
    ) => {
        event.preventDefault();

        clearErrors();

        post('/pickup-persons', {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout>
            <Head title="Tambah Penjemput" />

            <main className="min-h-full bg-[#f8fafc]">
                <div className="mx-auto w-full max-w-6xl p-4 md:p-6">
                    <Link
                        href="/pickup-persons"
                        className="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[#627d98] transition hover:text-[#4f7cac]"
                    >
                        <ArrowLeft
                            aria-hidden="true"
                            className="size-4"
                        />

                        Kembali ke Data Penjemput
                    </Link>

                    <section className="relative mb-6 overflow-hidden rounded-[28px] border border-[#deebf5] bg-gradient-to-r from-[#edf6ff] via-[#f2faf8] to-[#fffaf0] p-6 shadow-sm md:p-8">
                        <div className="absolute -top-20 -right-16 size-52 rounded-full bg-white/50 blur-3xl" />

                        <div className="relative flex items-start gap-4">
                            <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white text-[#5b8def] shadow-sm">
                                <UserRoundCheck className="size-6" />
                            </div>

                            <div>
                                <h1 className="text-2xl font-bold text-[#243b53] md:text-3xl">
                                    Tambah Penjemput
                                </h1>

                                <p className="mt-2 max-w-2xl text-sm leading-6 text-[#627d98]">
                                    Lengkapi identitas penjemput dan
                                    hubungkan dengan siswa yang boleh
                                    dijemput.
                                </p>
                            </div>
                        </div>
                    </section>

                    {Object.keys(errors).length > 0 && (
                        <section
                            role="alert"
                            className="mb-6 rounded-2xl border border-[#f0cece] bg-[#fff4f4] p-4"
                        >
                            <div className="flex items-start gap-3">
                                <AlertCircle className="mt-0.5 size-5 shrink-0 text-[#cf6464]" />

                                <div>
                                    <h2 className="text-sm font-semibold text-[#a64f4f]">
                                        Data belum dapat disimpan
                                    </h2>

                                    <p className="mt-1 text-sm text-[#b46363]">
                                        Periksa kembali kolom yang
                                        ditandai merah.
                                    </p>
                                </div>
                            </div>
                        </section>
                    )}

                    <form
                        onSubmit={submit}
                        className="space-y-6"
                        noValidate
                    >
                        <section className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-7">
                            <div className="mb-6 flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-xl bg-[#eef6ff] text-[#5b8def]">
                                    <UserRoundCheck className="size-5" />
                                </div>

                                <div>
                                    <h2 className="font-bold text-[#243b53]">
                                        Identitas Penjemput
                                    </h2>

                                    <p className="text-sm text-[#829ab1]">
                                        Informasi utama pihak yang
                                        diizinkan menjemput siswa.
                                    </p>
                                </div>
                            </div>

                            <div className="grid gap-5 md:grid-cols-2">
                                <div className="md:col-span-2">
                                    <label
                                        htmlFor="full_name"
                                        className="mb-2 block text-sm font-semibold text-[#334e68]"
                                    >
                                        Nama lengkap
                                        <span className="ml-1 text-[#e97a7a]">
                                            *
                                        </span>
                                    </label>

                                    <input
                                        id="full_name"
                                        type="text"
                                        value={data.full_name}
                                        onChange={(event) => {
                                            setData(
                                                'full_name',
                                                event.currentTarget.value,
                                            );

                                            clearErrors('full_name');
                                        }}
                                        disabled={processing}
                                        placeholder="Contoh: Budi Pratama"
                                        className={inputClass(
                                            Boolean(errors.full_name),
                                        )}
                                    />

                                    <InputError
                                        message={errors.full_name}
                                        className="mt-2"
                                    />
                                </div>

                                <div>
                                    <label
                                        htmlFor="identity_number"
                                        className="mb-2 block text-sm font-semibold text-[#334e68]"
                                    >
                                        Nomor identitas
                                    </label>

                                    <div className="relative">
                                        <IdCard className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#9fb3c8]" />

                                        <input
                                            id="identity_number"
                                            type="text"
                                            inputMode="numeric"
                                            maxLength={30}
                                            value={data.identity_number}
                                            onChange={(event) => {
                                                const value =
                                                    event.currentTarget.value
                                                        .replace(/\D/g, '')
                                                        .slice(0, 30);

                                                setData(
                                                    'identity_number',
                                                    value,
                                                );

                                                clearErrors(
                                                    'identity_number',
                                                );
                                            }}
                                            disabled={processing}
                                            placeholder="Nomor KTP atau identitas"
                                            className={inputClass(
                                                Boolean(
                                                    errors.identity_number,
                                                ),
                                                true,
                                            )}
                                        />
                                    </div>

                                    <InputError
                                        message={errors.identity_number}
                                        className="mt-2"
                                    />
                                </div>

                                <div>
                                    <label
                                        htmlFor="phone"
                                        className="mb-2 block text-sm font-semibold text-[#334e68]"
                                    >
                                        Nomor telepon
                                        <span className="ml-1 text-[#e97a7a]">
                                            *
                                        </span>
                                    </label>

                                    <div className="relative">
                                        <Phone className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#9fb3c8]" />

                                        <input
                                            id="phone"
                                            type="tel"
                                            value={data.phone}
                                            onChange={(event) => {
                                                setData(
                                                    'phone',
                                                    event.currentTarget.value,
                                                );

                                                clearErrors('phone');
                                            }}
                                            disabled={processing}
                                            placeholder="Contoh: 081298765401"
                                            className={inputClass(
                                                Boolean(errors.phone),
                                                true,
                                            )}
                                        />
                                    </div>

                                    <InputError
                                        message={errors.phone}
                                        className="mt-2"
                                    />
                                </div>

                                <div>
                                    <label
                                        htmlFor="email"
                                        className="mb-2 block text-sm font-semibold text-[#334e68]"
                                    >
                                        Email
                                    </label>

                                    <div className="relative">
                                        <Mail className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#9fb3c8]" />

                                        <input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(event) => {
                                                setData(
                                                    'email',
                                                    event.currentTarget.value,
                                                );

                                                clearErrors('email');
                                            }}
                                            disabled={processing}
                                            placeholder="nama@email.com"
                                            className={inputClass(
                                                Boolean(errors.email),
                                                true,
                                            )}
                                        />
                                    </div>

                                    <InputError
                                        message={errors.email}
                                        className="mt-2"
                                    />
                                </div>

                                <div>
                                    <label
                                        htmlFor="face_status"
                                        className="mb-2 block text-sm font-semibold text-[#334e68]"
                                    >
                                        Status wajah
                                        <span className="ml-1 text-[#e97a7a]">
                                            *
                                        </span>
                                    </label>

                                    <div className="relative">
                                        <ScanFace className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#9fb3c8]" />

                                        <select
                                            id="face_status"
                                            value={data.face_status}
                                            onChange={(event) => {
                                                setData(
                                                    'face_status',
                                                    event.currentTarget
                                                        .value as FaceStatus,
                                                );

                                                clearErrors('face_status');
                                            }}
                                            disabled={processing}
                                            className={inputClass(
                                                Boolean(errors.face_status),
                                                true,
                                            )}
                                        >
                                            <option value="not_registered">
                                                Belum terdaftar
                                            </option>

                                            <option value="registered">
                                                Wajah terdaftar
                                            </option>

                                            <option value="needs_update">
                                                Perlu diperbarui
                                            </option>
                                        </select>
                                    </div>

                                    <InputError
                                        message={errors.face_status}
                                        className="mt-2"
                                    />
                                </div>

                                <div className="md:col-span-2">
                                    <label
                                        htmlFor="address"
                                        className="mb-2 block text-sm font-semibold text-[#334e68]"
                                    >
                                        Alamat
                                    </label>

                                    <div className="relative">
                                        <MapPin className="pointer-events-none absolute top-3.5 left-3.5 size-4 text-[#9fb3c8]" />

                                        <textarea
                                            id="address"
                                            rows={4}
                                            maxLength={2000}
                                            value={data.address}
                                            onChange={(event) => {
                                                setData(
                                                    'address',
                                                    event.currentTarget.value,
                                                );

                                                clearErrors('address');
                                            }}
                                            disabled={processing}
                                            placeholder="Alamat lengkap penjemput..."
                                            className={`${textareaClass(
                                                Boolean(errors.address),
                                            )} pl-11`}
                                        />
                                    </div>

                                    <InputError
                                        message={errors.address}
                                        className="mt-2"
                                    />
                                </div>
                            </div>
                        </section>

                        <section className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-7">
                            <div className="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-10 items-center justify-center rounded-xl bg-[#eef9f6] text-[#4c9e94]">
                                        <UsersRound className="size-5" />
                                    </div>

                                    <div>
                                        <h2 className="font-bold text-[#243b53]">
                                            Siswa yang Dijemput
                                        </h2>

                                        <p className="text-sm text-[#829ab1]">
                                            Pilih minimal satu siswa
                                            beserta jenis hubungannya.
                                        </p>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    onClick={addStudentLink}
                                    disabled={
                                        processing ||
                                        data.students.length >= 20
                                    }
                                    className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#cfe4f6] bg-[#eef6ff] px-4 text-sm font-semibold text-[#4f7cac] transition hover:bg-[#e2f0fb] disabled:opacity-50"
                                >
                                    <Plus className="size-4" />
                                    Tambah Siswa
                                </button>
                            </div>

                            <InputError
                                message={errors.students}
                                className="mb-4"
                            />

                            <div className="space-y-4">
                                {data.students.map(
                                    (studentLink, index) => {
                                        const selectedStudent =
                                            students.find(
                                                (student) =>
                                                    String(student.id) ===
                                                    studentLink.student_id,
                                            );

                                        return (
                                            <article
                                                key={index}
                                                className="rounded-2xl border border-[#e6eef5] bg-[#fbfdff] p-4"
                                            >
                                                <div className="mb-4 flex items-center justify-between gap-4">
                                                    <h3 className="text-sm font-bold text-[#334e68]">
                                                        Siswa {index + 1}
                                                    </h3>

                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            removeStudentLink(
                                                                index,
                                                            )
                                                        }
                                                        disabled={processing}
                                                        title="Hapus hubungan siswa"
                                                        className="inline-flex size-9 items-center justify-center rounded-xl border border-[#f0d0d0] bg-white text-[#cf6464] transition hover:bg-[#fff2f2]"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </button>
                                                </div>

                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <div>
                                                        <label
                                                            htmlFor={`student-${index}`}
                                                            className="mb-2 block text-sm font-semibold text-[#334e68]"
                                                        >
                                                            Siswa
                                                            <span className="ml-1 text-[#e97a7a]">
                                                                *
                                                            </span>
                                                        </label>

                                                        <select
                                                            id={`student-${index}`}
                                                            value={
                                                                studentLink.student_id
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) =>
                                                                updateStudentLink(
                                                                    index,
                                                                    'student_id',
                                                                    event
                                                                        .currentTarget
                                                                        .value,
                                                                )
                                                            }
                                                            disabled={
                                                                processing
                                                            }
                                                            className={inputClass(
                                                                Boolean(
                                                                    fieldError(
                                                                        `students.${index}.student_id`,
                                                                    ),
                                                                ),
                                                            )}
                                                        >
                                                            <option value="">
                                                                Pilih siswa
                                                            </option>

                                                            {students.map(
                                                                (student) => (
                                                                    <option
                                                                        key={
                                                                            student.id
                                                                        }
                                                                        value={
                                                                            student.id
                                                                        }
                                                                        disabled={data.students.some(
                                                                            (
                                                                                link,
                                                                                linkIndex,
                                                                            ) =>
                                                                                linkIndex !==
                                                                                    index &&
                                                                                link.student_id ===
                                                                                    String(
                                                                                        student.id,
                                                                                    ),
                                                                        )}
                                                                    >
                                                                        {
                                                                            student.full_name
                                                                        }{' '}
                                                                        ·{' '}
                                                                        {
                                                                            student.student_number
                                                                        }
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>

                                                        <InputError
                                                            message={fieldError(
                                                                `students.${index}.student_id`,
                                                            )}
                                                            className="mt-2"
                                                        />

                                                        {selectedStudent && (
                                                            <p className="mt-2 text-xs text-[#829ab1]">
                                                                Kelas{' '}
                                                                {selectedStudent.class_name ??
                                                                    '-'}{' '}
                                                                ·{' '}
                                                                {selectedStudent.academic_year ??
                                                                    '-'}
                                                            </p>
                                                        )}
                                                    </div>

                                                    <div>
                                                        <label
                                                            htmlFor={`relationship-${index}`}
                                                            className="mb-2 block text-sm font-semibold text-[#334e68]"
                                                        >
                                                            Hubungan
                                                            <span className="ml-1 text-[#e97a7a]">
                                                                *
                                                            </span>
                                                        </label>

                                                        <select
                                                            id={`relationship-${index}`}
                                                            value={
                                                                studentLink.relationship_type
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) =>
                                                                updateStudentLink(
                                                                    index,
                                                                    'relationship_type',
                                                                    event
                                                                        .currentTarget
                                                                        .value as RelationshipType,
                                                                )
                                                            }
                                                            disabled={
                                                                processing
                                                            }
                                                            className={inputClass(
                                                                Boolean(
                                                                    fieldError(
                                                                        `students.${index}.relationship_type`,
                                                                    ),
                                                                ),
                                                            )}
                                                        >
                                                            <option value="">
                                                                Pilih hubungan
                                                            </option>

                                                            {relationshipOptions.map(
                                                                (
                                                                    option,
                                                                ) => (
                                                                    <option
                                                                        key={
                                                                            option.value
                                                                        }
                                                                        value={
                                                                            option.value
                                                                        }
                                                                    >
                                                                        {
                                                                            option.label
                                                                        }
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>

                                                        <InputError
                                                            message={fieldError(
                                                                `students.${index}.relationship_type`,
                                                            )}
                                                            className="mt-2"
                                                        />
                                                    </div>

                                                    <div>
                                                        <label
                                                            htmlFor={`valid-from-${index}`}
                                                            className="mb-2 block text-sm font-semibold text-[#334e68]"
                                                        >
                                                            Berlaku mulai
                                                        </label>

                                                        <div className="relative">
                                                            <CalendarDays className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#9fb3c8]" />

                                                            <input
                                                                id={`valid-from-${index}`}
                                                                type="date"
                                                                value={
                                                                    studentLink.valid_from
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateStudentLink(
                                                                        index,
                                                                        'valid_from',
                                                                        event
                                                                            .currentTarget
                                                                            .value,
                                                                    )
                                                                }
                                                                disabled={
                                                                    processing
                                                                }
                                                                className={inputClass(
                                                                    Boolean(
                                                                        fieldError(
                                                                            `students.${index}.valid_from`,
                                                                        ),
                                                                    ),
                                                                    true,
                                                                )}
                                                            />
                                                        </div>

                                                        <InputError
                                                            message={fieldError(
                                                                `students.${index}.valid_from`,
                                                            )}
                                                            className="mt-2"
                                                        />
                                                    </div>

                                                    <div>
                                                        <label
                                                            htmlFor={`valid-until-${index}`}
                                                            className="mb-2 block text-sm font-semibold text-[#334e68]"
                                                        >
                                                            Berlaku sampai
                                                        </label>

                                                        <div className="relative">
                                                            <CalendarDays className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#9fb3c8]" />

                                                            <input
                                                                id={`valid-until-${index}`}
                                                                type="date"
                                                                min={
                                                                    studentLink.valid_from ||
                                                                    undefined
                                                                }
                                                                value={
                                                                    studentLink.valid_until
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateStudentLink(
                                                                        index,
                                                                        'valid_until',
                                                                        event
                                                                            .currentTarget
                                                                            .value,
                                                                    )
                                                                }
                                                                disabled={
                                                                    processing
                                                                }
                                                                className={inputClass(
                                                                    Boolean(
                                                                        fieldError(
                                                                            `students.${index}.valid_until`,
                                                                        ),
                                                                    ),
                                                                    true,
                                                                )}
                                                            />
                                                        </div>

                                                        <InputError
                                                            message={fieldError(
                                                                `students.${index}.valid_until`,
                                                            )}
                                                            className="mt-2"
                                                        />
                                                    </div>
                                                </div>

                                                <div className="mt-4 flex flex-wrap gap-5 rounded-xl bg-white p-3">
                                                    <label className="flex cursor-pointer items-center gap-2 text-sm font-medium text-[#627d98]">
                                                        <input
                                                            type="checkbox"
                                                            checked={
                                                                studentLink.is_primary
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) =>
                                                                updateStudentLink(
                                                                    index,
                                                                    'is_primary',
                                                                    event
                                                                        .currentTarget
                                                                        .checked,
                                                                )
                                                            }
                                                            disabled={
                                                                processing
                                                            }
                                                            className="size-4 rounded border-[#bcccdc] text-[#5b8def]"
                                                        />

                                                        Penjemput utama
                                                    </label>

                                                    <label className="flex cursor-pointer items-center gap-2 text-sm font-medium text-[#627d98]">
                                                        <input
                                                            type="checkbox"
                                                            checked={
                                                                studentLink.is_active
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) =>
                                                                updateStudentLink(
                                                                    index,
                                                                    'is_active',
                                                                    event
                                                                        .currentTarget
                                                                        .checked,
                                                                )
                                                            }
                                                            disabled={
                                                                processing
                                                            }
                                                            className="size-4 rounded border-[#bcccdc] text-[#5b8def]"
                                                        />

                                                        Izin aktif
                                                    </label>
                                                </div>
                                            </article>
                                        );
                                    },
                                )}
                            </div>
                        </section>

                        <section className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-7">
                            <h2 className="font-bold text-[#243b53]">
                                Status dan Catatan
                            </h2>

                            <div className="mt-5 grid gap-5 md:grid-cols-2">
                                <div>
                                    <label className="flex cursor-pointer items-center gap-3 rounded-xl border border-[#d9e5ee] bg-[#fbfdff] p-4">
                                        <input
                                            type="checkbox"
                                            checked={data.is_active}
                                            onChange={(event) => {
                                                setData(
                                                    'is_active',
                                                    event.currentTarget
                                                        .checked,
                                                );

                                                clearErrors('is_active');
                                            }}
                                            disabled={processing}
                                            className="size-4 rounded border-[#bcccdc] text-[#5b8def]"
                                        />

                                        <span>
                                            <span className="block text-sm font-semibold text-[#334e68]">
                                                Penjemput aktif
                                            </span>

                                            <span className="mt-1 block text-xs text-[#829ab1]">
                                                Penjemput dapat digunakan
                                                dalam proses penjemputan.
                                            </span>
                                        </span>
                                    </label>

                                    <InputError
                                        message={errors.is_active}
                                        className="mt-2"
                                    />
                                </div>

                                <div>
                                    <label
                                        htmlFor="notes"
                                        className="mb-2 block text-sm font-semibold text-[#334e68]"
                                    >
                                        Catatan
                                    </label>

                                    <textarea
                                        id="notes"
                                        rows={5}
                                        maxLength={2000}
                                        value={data.notes}
                                        onChange={(event) => {
                                            setData(
                                                'notes',
                                                event.currentTarget.value,
                                            );

                                            clearErrors('notes');
                                        }}
                                        disabled={processing}
                                        placeholder="Catatan tambahan mengenai penjemput..."
                                        className={textareaClass(
                                            Boolean(errors.notes),
                                        )}
                                    />

                                    <InputError
                                        message={errors.notes}
                                        className="mt-2"
                                    />
                                </div>
                            </div>
                        </section>

                        <section className="flex flex-col-reverse gap-3 rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm sm:flex-row sm:justify-end">
                            <Link
                                href="/pickup-persons"
                                className="inline-flex h-11 items-center justify-center rounded-xl border border-[#d9e5ee] px-5 text-sm font-semibold text-[#627d98] transition hover:bg-[#f7fafc]"
                            >
                                Batal
                            </Link>

                            <button
                                type="submit"
                                disabled={
                                    processing ||
                                    students.length === 0
                                }
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
                                        Simpan Penjemput
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