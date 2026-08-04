import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { type FormEvent, type MouseEvent, useEffect, useRef, useState } from 'react';

type NumericValue = number | string | null;

interface OfficerSummary {
    id: number;
    name: string;
}

interface PickupEventHistoryItem {
    id: number;
    status: string;
    status_label: string;
    verification_method: string;
    verification_method_label: string;
    pickup_person_name: string;
    pickup_person_phone: string | null;
    confirmed_at: string | null;
    cancelled_at: string | null;
    confirmed_by: OfficerSummary | null;
    cancelled_by: OfficerSummary | null;
    student_count: number;
    released_student_count: number;
    cancelled_student_count: number;
    can_cancel: boolean;
    url: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedPickupEvents {
    current_page: number;
    data: PickupEventHistoryItem[];
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    per_page: number;
    to: number | null;
    total: number;
}

interface HistoryFilters {
    date_from: string | null;
    date_to: string | null;
    status: string | null;
    verification_method: string | null;
    confirmed_by_user_id: number | null;
    search: string | null;
    per_page: number;
}

interface FilterOption {
    value: string;
    label: string;
}

interface FilterOptions {
    statuses: FilterOption[];
    verification_methods: FilterOption[];
    officers: OfficerSummary[];
    per_page_options: number[];
}

interface HistorySummary {
    total_transactions: number;
    confirmed_transactions: number;
    cancelled_transactions: number;
    released_students: number;
    cancelled_students: number;
}

interface VerificationAttempt {
    id: number;
    result: string;
    similarity_score: NumericValue;
    similarity_threshold: NumericValue;
    candidate_margin: NumericValue;
    quality_score: NumericValue;
    liveness_passed: boolean;
    live_score: NumericValue;
    real_score: NumericValue;
    model_name: string;
    model_version: string | null;
    occurred_at: string | null;
}

interface PickupEventDetailStudent {
    id: number;
    student_id: number | null;
    student_name: string;
    student_number: string | null;
    class_name: string | null;
    academic_year: string | null;
    relationship_type: string | null;
    is_primary: boolean;
    status: string;
    status_label: string;
    released_at: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    cancelled_by: OfficerSummary | null;
    can_cancel: boolean;
}

interface PickupEventDetail {
    id: number;
    idempotency_key: string;
    status: string;
    status_label: string;
    verification_method: string;
    verification_method_label: string;
    verification_result: string;
    similarity_score: NumericValue;
    similarity_threshold: NumericValue;
    candidate_margin: NumericValue;
    confirmed_at: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    notes: string | null;
    can_cancel: boolean;

    pickup_person: {
        id: number | null;
        full_name: string;
        phone: string | null;
    };

    confirmed_by: OfficerSummary | null;
    cancelled_by: OfficerSummary | null;
    verification_attempt: VerificationAttempt | null;
    students: PickupEventDetailStudent[];
}

interface DetailResponse {
    pickup_event: PickupEventDetail;
}

interface MutationResponse {
    message: string;
    pickup_event: PickupEventDetail;
}

interface LaravelErrorPayload {
    message?: string;
    errors?: Record<string, string[] | string>;
}

interface PageProps {
    pickupEvents: PaginatedPickupEvents;
    summary: HistorySummary;
    filters: HistoryFilters;
    filterOptions: FilterOptions;
}

type CancellationTarget =
    | {
          type: 'event';
          eventId: number;
          title: string;
      }
    | {
          type: 'student';
          eventId: number;
          eventStudentId: number;
          title: string;
      };

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Verifikasi Gerbang',
        href: '/gate/face-verification',
    },
    {
        title: 'Riwayat Gerbang',
        href: '/gate/pickup-events',
    },
];

function numericValue(value: NumericValue): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number(value);

        return Number.isFinite(parsed) ? parsed : null;
    }

    return null;
}

function percentage(value: NumericValue): string {
    const normalized = numericValue(value);

    return normalized === null ? '-' : `${Math.round(normalized * 100)}%`;
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat('id-ID').format(Number.isFinite(value) ? value : 0);
}

function relationshipLabel(value: string | null): string {
    const labels: Record<string, string> = {
        father: 'Ayah',
        mother: 'Ibu',
        guardian: 'Wali',
        sibling: 'Saudara',
        grandparent: 'Kakek/Nenek',
        driver: 'Sopir',
        relative: 'Kerabat',
    };

    return value ? (labels[value] ?? value) : 'Lainnya';
}

function statusBadgeClass(status: string): string {
    if (status === 'confirmed' || status === 'released') {
        return 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300';
    }

    if (status === 'cancelled') {
        return 'border-red-300 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300';
    }

    return 'border-border bg-muted text-muted-foreground';
}

function paginationLabel(label: string): string {
    return label
        .replace(/&laquo;/gi, '‹')
        .replace(/&raquo;/gi, '›')
        .replace(/<[^>]*>/g, '');
}

function csrfHeaders(): Record<string, string> {
    const metaToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

    if (metaToken) {
        return {
            'X-CSRF-TOKEN': metaToken,
        };
    }

    const xsrfCookie = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='));

    return xsrfCookie
        ? {
              'X-XSRF-TOKEN': decodeURIComponent(xsrfCookie.substring('XSRF-TOKEN='.length)),
          }
        : {};
}

async function readResponsePayload(response: globalThis.Response): Promise<unknown> {
    const contentType = response.headers.get('content-type') ?? '';

    if (contentType.includes('json')) {
        try {
            return await response.json();
        } catch {
            return null;
        }
    }

    try {
        const text = await response.text();

        return text.trim()
            ? {
                  message: text,
              }
            : null;
    } catch {
        return null;
    }
}

function errorPayload(payload: unknown): LaravelErrorPayload | null {
    return typeof payload === 'object' && payload !== null ? (payload as LaravelErrorPayload) : null;
}

function validationMessages(payload: LaravelErrorPayload | null): string[] {
    if (!payload?.errors) {
        return [];
    }

    return Object.values(payload.errors)
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .map(String)
        .filter(Boolean);
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function isPositiveInteger(value: unknown): value is number {
    return typeof value === 'number' && Number.isInteger(value) && value > 0;
}

function isNullableString(value: unknown): value is string | null {
    return value === null || typeof value === 'string';
}

function isNumericValue(value: unknown): value is NumericValue {
    return (
        value === null ||
        (typeof value === 'number' && Number.isFinite(value)) ||
        (typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value)))
    );
}

function isOfficer(payload: unknown): payload is OfficerSummary {
    return isRecord(payload) && isPositiveInteger(payload.id) && typeof payload.name === 'string';
}

function isNullableOfficer(payload: unknown): payload is OfficerSummary | null {
    return payload === null || isOfficer(payload);
}

function isVerificationAttempt(payload: unknown): payload is VerificationAttempt {
    return (
        isRecord(payload) &&
        isPositiveInteger(payload.id) &&
        typeof payload.result === 'string' &&
        isNumericValue(payload.similarity_score) &&
        isNumericValue(payload.similarity_threshold) &&
        isNumericValue(payload.candidate_margin) &&
        isNumericValue(payload.quality_score) &&
        typeof payload.liveness_passed === 'boolean' &&
        isNumericValue(payload.live_score) &&
        isNumericValue(payload.real_score) &&
        typeof payload.model_name === 'string' &&
        isNullableString(payload.model_version) &&
        isNullableString(payload.occurred_at)
    );
}

function isDetailStudent(payload: unknown): payload is PickupEventDetailStudent {
    return (
        isRecord(payload) &&
        isPositiveInteger(payload.id) &&
        (payload.student_id === null || isPositiveInteger(payload.student_id)) &&
        typeof payload.student_name === 'string' &&
        isNullableString(payload.student_number) &&
        isNullableString(payload.class_name) &&
        isNullableString(payload.academic_year) &&
        isNullableString(payload.relationship_type) &&
        typeof payload.is_primary === 'boolean' &&
        typeof payload.status === 'string' &&
        typeof payload.status_label === 'string' &&
        isNullableString(payload.released_at) &&
        isNullableString(payload.cancelled_at) &&
        isNullableString(payload.cancellation_reason) &&
        isNullableOfficer(payload.cancelled_by) &&
        typeof payload.can_cancel === 'boolean'
    );
}

function isPickupEventDetail(payload: unknown): payload is PickupEventDetail {
    if (!isRecord(payload) || !isRecord(payload.pickup_person)) {
        return false;
    }

    const pickupPerson = payload.pickup_person;

    return (
        isPositiveInteger(payload.id) &&
        typeof payload.idempotency_key === 'string' &&
        typeof payload.status === 'string' &&
        typeof payload.status_label === 'string' &&
        typeof payload.verification_method === 'string' &&
        typeof payload.verification_method_label === 'string' &&
        typeof payload.verification_result === 'string' &&
        isNumericValue(payload.similarity_score) &&
        isNumericValue(payload.similarity_threshold) &&
        isNumericValue(payload.candidate_margin) &&
        isNullableString(payload.confirmed_at) &&
        isNullableString(payload.cancelled_at) &&
        isNullableString(payload.cancellation_reason) &&
        isNullableString(payload.notes) &&
        typeof payload.can_cancel === 'boolean' &&
        (pickupPerson.id === null || isPositiveInteger(pickupPerson.id)) &&
        typeof pickupPerson.full_name === 'string' &&
        isNullableString(pickupPerson.phone) &&
        isNullableOfficer(payload.confirmed_by) &&
        isNullableOfficer(payload.cancelled_by) &&
        (payload.verification_attempt === null || isVerificationAttempt(payload.verification_attempt)) &&
        Array.isArray(payload.students) &&
        payload.students.every(isDetailStudent)
    );
}

function isDetailResponse(payload: unknown): payload is DetailResponse {
    return isRecord(payload) && isPickupEventDetail(payload.pickup_event);
}

function isMutationResponse(payload: unknown): payload is MutationResponse {
    return isRecord(payload) && typeof payload.message === 'string' && isPickupEventDetail(payload.pickup_event);
}

function StatusBadge({ status, label }: { status: string; label: string }) {
    return <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${statusBadgeClass(status)}`}>{label}</span>;
}

function SummaryCard({
    label,
    value,
    description,
    tone = 'default',
}: {
    label: string;
    value: number;
    description: string;
    tone?: 'default' | 'green' | 'red' | 'blue' | 'amber';
}) {
    const classes = {
        default: 'border-border bg-card text-foreground',

        green: 'border-emerald-200 bg-emerald-50/60 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',

        red: 'border-red-200 bg-red-50/60 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300',

        blue: 'border-blue-200 bg-blue-50/60 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300',

        amber: 'border-amber-200 bg-amber-50/60 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300',
    };

    return (
        <article className={`module-summary-card module-history-card rounded-[22px] border p-4 ${classes[tone]}`}>
            <p className="text-xs font-medium tracking-wide uppercase opacity-80">{label}</p>

            <p className="mt-3 text-2xl font-bold">{formatNumber(value)}</p>

            <p className="mt-1 text-xs opacity-70">{description}</p>
        </article>
    );
}

function backdropClicked(event: MouseEvent<HTMLDivElement>): boolean {
    return event.target === event.currentTarget;
}

function DetailModal({
    open,
    loading,
    error,
    detail,
    actionMessage,
    isCancelling,
    cancellationDialogOpen,
    onClose,
    onCancelEvent,
    onCancelStudent,
}: {
    open: boolean;
    loading: boolean;
    error: string | null;
    detail: PickupEventDetail | null;
    actionMessage: string | null;
    isCancelling: boolean;
    cancellationDialogOpen: boolean;
    onClose: () => void;
    onCancelEvent: (event: PickupEventDetail) => void;
    onCancelStudent: (event: PickupEventDetail, student: PickupEventDetailStudent) => void;
}) {
    if (!open) {
        return null;
    }

    return (
        <div
            className="fixed inset-0 z-40 flex items-center justify-center bg-black/60 p-3 md:p-6"
            onMouseDown={(event) => {
                if (backdropClicked(event) && !isCancelling && !cancellationDialogOpen) {
                    onClose();
                }
            }}
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="pickup-event-detail-title"
                aria-busy={loading}
                className="module-history-modal bg-background max-h-[94vh] w-full max-w-4xl overflow-y-auto rounded-[26px] border shadow-xl"
            >
                <header className="bg-background sticky top-0 z-10 flex items-center justify-between gap-4 border-b px-4 py-4 md:px-6">
                    <div>
                        <p className="text-muted-foreground text-xs">Riwayat Gerbang</p>

                        <h2 id="pickup-event-detail-title" className="font-bold">
                            {detail ? `Detail Transaksi #${detail.id}` : 'Detail Transaksi'}
                        </h2>
                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        disabled={isCancelling}
                        className="module-secondary-button inline-flex h-9 items-center justify-center rounded-xl border px-3 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Tutup
                    </button>
                </header>

                {loading && <div className="text-muted-foreground p-10 text-center text-sm">Memuat detail transaksi...</div>}

                {error && (
                    <div className="p-6">
                        <div
                            role="alert"
                            className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                        >
                            {error}
                        </div>
                    </div>
                )}

                {detail && (
                    <div className="space-y-6 p-4 md:p-6">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-muted-foreground text-sm">Status Transaksi</p>

                                <div className="mt-2">
                                    <StatusBadge status={detail.status} label={detail.status_label} />
                                </div>
                            </div>

                            {detail.can_cancel && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        onCancelEvent(detail);
                                    }}
                                    className="module-danger-solid inline-flex h-10 items-center justify-center rounded-xl px-4 text-sm font-bold text-white"
                                >
                                    Batalkan Transaksi
                                </button>
                            )}
                        </div>

                        {actionMessage && (
                            <div
                                role="status"
                                aria-live="polite"
                                className="rounded-md border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300"
                            >
                                {actionMessage}
                            </div>
                        )}

                        <section className="rounded-lg border p-4">
                            <h3 className="font-semibold">Informasi Penjemputan</h3>

                            <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <dt className="text-muted-foreground">Penjemput</dt>

                                    <dd className="mt-1 font-semibold">{detail.pickup_person.full_name}</dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">Telepon</dt>

                                    <dd className="mt-1 font-semibold">{detail.pickup_person.phone || '-'}</dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">Metode</dt>

                                    <dd className="mt-1 font-semibold">{detail.verification_method_label}</dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">Waktu Konfirmasi</dt>

                                    <dd className="mt-1 font-semibold">{formatDateTime(detail.confirmed_at)}</dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">Petugas</dt>

                                    <dd className="mt-1 font-semibold">{detail.confirmed_by?.name || '-'}</dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">Similarity</dt>

                                    <dd className="mt-1 font-semibold">{percentage(detail.similarity_score)}</dd>
                                </div>
                            </dl>

                            {detail.notes && (
                                <div className="mt-4 border-t pt-4">
                                    <p className="text-muted-foreground text-sm">Catatan</p>

                                    <p className="mt-1 text-sm whitespace-pre-wrap">{detail.notes}</p>
                                </div>
                            )}

                            {detail.status === 'cancelled' && (
                                <div className="mt-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm dark:border-red-900 dark:bg-red-950">
                                    <p className="font-semibold text-red-700 dark:text-red-300">Transaksi Dibatalkan</p>

                                    <p className="mt-2">{detail.cancellation_reason || '-'}</p>

                                    <p className="text-muted-foreground mt-2 text-xs">
                                        {detail.cancelled_by?.name || 'Petugas tidak tersedia'}
                                        {' • '}
                                        {formatDateTime(detail.cancelled_at)}
                                    </p>
                                </div>
                            )}
                        </section>

                        {detail.verification_attempt && (
                            <section className="rounded-lg border p-4">
                                <h3 className="font-semibold">Audit Verifikasi Wajah</h3>

                                <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <dt className="text-muted-foreground">Attempt</dt>

                                        <dd className="mt-1 font-semibold">#{detail.verification_attempt.id}</dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">Similarity</dt>

                                        <dd className="mt-1 font-semibold">{percentage(detail.verification_attempt.similarity_score)}</dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">Threshold</dt>

                                        <dd className="mt-1 font-semibold">{percentage(detail.verification_attempt.similarity_threshold)}</dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">Margin</dt>

                                        <dd className="mt-1 font-semibold">{percentage(detail.verification_attempt.candidate_margin)}</dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">Kualitas</dt>

                                        <dd className="mt-1 font-semibold">{percentage(detail.verification_attempt.quality_score)}</dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">Live</dt>

                                        <dd className="mt-1 font-semibold">{percentage(detail.verification_attempt.live_score)}</dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">Real</dt>

                                        <dd className="mt-1 font-semibold">{percentage(detail.verification_attempt.real_score)}</dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">Liveness</dt>

                                        <dd
                                            className={`mt-1 font-semibold ${
                                                detail.verification_attempt.liveness_passed ? 'text-emerald-600' : 'text-red-600'
                                            }`}
                                        >
                                            {detail.verification_attempt.liveness_passed ? 'Lulus' : 'Gagal'}
                                        </dd>
                                    </div>
                                </dl>
                            </section>
                        )}

                        <section className="rounded-lg border">
                            <div className="border-b px-4 py-4">
                                <h3 className="font-semibold">Siswa dalam Transaksi</h3>

                                <p className="text-muted-foreground mt-1 text-sm">{detail.students.length} siswa tercatat.</p>
                            </div>

                            <div className="divide-y">
                                {detail.students.map((student) => (
                                    <article key={student.id} className="p-4">
                                        <div className="module-history-hero flex flex-col gap-4 rounded-[28px] p-6 sm:flex-row sm:items-center sm:justify-between md:p-8">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-semibold">{student.student_name}</p>

                                                    {student.is_primary && (
                                                        <span className="bg-primary/10 text-primary rounded-full px-2 py-0.5 text-xs font-medium">
                                                            Utama
                                                        </span>
                                                    )}

                                                    <StatusBadge status={student.status} label={student.status_label} />
                                                </div>

                                                <p className="text-muted-foreground mt-2 text-sm">
                                                    {student.student_number || '-'}
                                                    {' • '}
                                                    {student.class_name || 'Tanpa kelas'}
                                                    {' • '}
                                                    {relationshipLabel(student.relationship_type)}
                                                </p>

                                                {student.academic_year && (
                                                    <p className="text-muted-foreground mt-1 text-xs">Tahun ajaran: {student.academic_year}</p>
                                                )}

                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    Diserahkan: {formatDateTime(student.released_at)}
                                                </p>

                                                {student.status === 'cancelled' && (
                                                    <div className="mt-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm dark:border-red-900 dark:bg-red-950">
                                                        <p>{student.cancellation_reason || '-'}</p>

                                                        <p className="text-muted-foreground mt-1 text-xs">
                                                            {student.cancelled_by?.name || '-'}
                                                            {' • '}
                                                            {formatDateTime(student.cancelled_at)}
                                                        </p>
                                                    </div>
                                                )}
                                            </div>

                                            {student.can_cancel && (
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        onCancelStudent(detail, student);
                                                    }}
                                                    className="module-danger-button inline-flex h-9 shrink-0 items-center justify-center rounded-xl border px-3 text-sm font-bold"
                                                >
                                                    Batalkan Siswa
                                                </button>
                                            )}
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </section>
                    </div>
                )}
            </div>
        </div>
    );
}

function CancellationDialog({
    target,
    reason,
    error,
    busy,
    onReasonChange,
    onClose,
    onSubmit,
}: {
    target: CancellationTarget | null;
    reason: string;
    error: string | null;
    busy: boolean;
    onReasonChange: (value: string) => void;
    onClose: () => void;
    onSubmit: () => void;
}) {
    if (!target) {
        return null;
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            onMouseDown={(event) => {
                if (backdropClicked(event) && !busy) {
                    onClose();
                }
            }}
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="cancellation-dialog-title"
                className="module-cancellation-modal bg-background w-full max-w-lg rounded-[24px] border p-5 shadow-xl"
            >
                <h2 id="cancellation-dialog-title" className="text-lg font-bold">
                    {target.title}
                </h2>

                <p className="text-muted-foreground mt-2 text-sm">Pembatalan akan dicatat bersama nama petugas, waktu, dan alasan pembatalan.</p>

                <label htmlFor="cancellation-reason" className="mt-5 block text-sm font-medium">
                    Alasan Pembatalan
                </label>

                <textarea
                    id="cancellation-reason"
                    value={reason}
                    onChange={(event) => {
                        onReasonChange(event.target.value.slice(0, 1000));
                    }}
                    disabled={busy}
                    rows={4}
                    maxLength={1000}
                    autoFocus
                    placeholder="Tuliskan alasan pembatalan..."
                    className="bg-background focus:border-primary mt-2 w-full rounded-md border px-3 py-2 text-sm outline-none disabled:opacity-60"
                />

                <p className="text-muted-foreground mt-1 text-right text-xs">{reason.length}/1000</p>

                {error && (
                    <div
                        role="alert"
                        className="mt-3 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                    >
                        {error}
                    </div>
                )}

                <div className="mt-5 grid gap-2 sm:grid-cols-2">
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={busy}
                        className="module-secondary-button inline-flex h-10 items-center justify-center rounded-xl border px-4 text-sm font-bold disabled:opacity-50"
                    >
                        Kembali
                    </button>

                    <button
                        type="button"
                        onClick={onSubmit}
                        disabled={busy || reason.trim().length < 5}
                        className="module-danger-solid inline-flex h-10 items-center justify-center rounded-xl px-4 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {busy ? 'Membatalkan...' : 'Konfirmasi Pembatalan'}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function PickupEventHistory({ pickupEvents, summary, filters, filterOptions }: PageProps) {
    const detailAbortRef = useRef<AbortController | null>(null);

    const cancellationAbortRef = useRef<AbortController | null>(null);

    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');

    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const [status, setStatus] = useState(filters.status ?? '');

    const [verificationMethod, setVerificationMethod] = useState(filters.verification_method ?? '');

    const [officerId, setOfficerId] = useState(filters.confirmed_by_user_id ? String(filters.confirmed_by_user_id) : '');

    const [search, setSearch] = useState(filters.search ?? '');

    const [perPage, setPerPage] = useState(String(filters.per_page));

    const [selectedDetail, setSelectedDetail] = useState<PickupEventDetail | null>(null);

    const [isLoadingDetail, setIsLoadingDetail] = useState(false);

    const [detailError, setDetailError] = useState<string | null>(null);

    const [cancellationTarget, setCancellationTarget] = useState<CancellationTarget | null>(null);

    const [cancellationReason, setCancellationReason] = useState('');

    const [isCancelling, setIsCancelling] = useState(false);

    const [actionMessage, setActionMessage] = useState<string | null>(null);

    const [actionError, setActionError] = useState<string | null>(null);

    const detailModalOpen = isLoadingDetail || detailError !== null || selectedDetail !== null;

    useEffect(() => {
        setDateFrom(filters.date_from ?? '');

        setDateTo(filters.date_to ?? '');

        setStatus(filters.status ?? '');

        setVerificationMethod(filters.verification_method ?? '');

        setOfficerId(filters.confirmed_by_user_id ? String(filters.confirmed_by_user_id) : '');

        setSearch(filters.search ?? '');

        setPerPage(String(filters.per_page));
    }, [
        filters.confirmed_by_user_id,
        filters.date_from,
        filters.date_to,
        filters.per_page,
        filters.search,
        filters.status,
        filters.verification_method,
    ]);

    useEffect(() => {
        return () => {
            detailAbortRef.current?.abort();

            cancellationAbortRef.current?.abort();
        };
    }, []);

    useEffect(() => {
        if (!detailModalOpen && !cancellationTarget) {
            return;
        }

        const previousOverflow = document.body.style.overflow;

        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, [cancellationTarget, detailModalOpen]);

    useEffect(() => {
        if (!detailModalOpen && !cancellationTarget) {
            return;
        }

        function handleKeyDown(event: KeyboardEvent): void {
            if (event.key !== 'Escape' || isCancelling) {
                return;
            }

            if (cancellationTarget) {
                cancellationAbortRef.current?.abort();

                cancellationAbortRef.current = null;

                setCancellationTarget(null);

                setCancellationReason('');
                setActionError(null);

                return;
            }

            detailAbortRef.current?.abort();

            detailAbortRef.current = null;

            setIsLoadingDetail(false);
            setSelectedDetail(null);
            setDetailError(null);
        }

        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [cancellationTarget, detailModalOpen, isCancelling]);

    function applyFilters(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        setActionError(null);
        setActionMessage(null);

        if (dateFrom && dateTo && dateTo < dateFrom) {
            setActionError('Tanggal akhir tidak boleh sebelum tanggal awal.');

            return;
        }

        const query: Record<string, string | number> = {};

        if (dateFrom) {
            query.date_from = dateFrom;
        }

        if (dateTo) {
            query.date_to = dateTo;
        }

        if (status) {
            query.status = status;
        }

        if (verificationMethod) {
            query.verification_method = verificationMethod;
        }

        const normalizedOfficerId = Number(officerId);

        if (officerId && Number.isInteger(normalizedOfficerId)) {
            query.confirmed_by_user_id = normalizedOfficerId;
        }

        const normalizedSearch = search.trim();

        if (normalizedSearch) {
            query.search = normalizedSearch;
        }

        const normalizedPerPage = Number(perPage);

        query.per_page = filterOptions.per_page_options.includes(normalizedPerPage) ? normalizedPerPage : filters.per_page;

        router.get('/gate/pickup-events', query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }

    function resetFilters(): void {
        setActionError(null);
        setActionMessage(null);
        setDateFrom('');
        setDateTo('');
        setStatus('');
        setVerificationMethod('');
        setOfficerId('');
        setSearch('');
        setPerPage('15');

        router.get(
            '/gate/pickup-events',
            {},
            {
                preserveScroll: true,
                preserveState: false,
                replace: true,
            },
        );
    }

    async function openDetail(pickupEventId: number): Promise<void> {
        const abortController = new AbortController();

        detailAbortRef.current?.abort();

        detailAbortRef.current = abortController;

        setSelectedDetail(null);
        setDetailError(null);
        setActionError(null);
        setActionMessage(null);
        setIsLoadingDetail(true);

        try {
            const response = await fetch(`/gate/pickup-events/${pickupEventId}`, {
                method: 'GET',

                credentials: 'same-origin',

                signal: abortController.signal,

                headers: {
                    Accept: 'application/json',

                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = await readResponsePayload(response);

            const backendError = errorPayload(payload);

            if (!response.ok) {
                if (response.status === 401 || response.status === 419) {
                    throw new Error('Sesi login telah berakhir. Muat ulang halaman lalu masuk kembali.');
                }

                if (response.status === 403) {
                    throw new Error(backendError?.message || 'Akun tidak memiliki izin melihat transaksi ini.');
                }

                if (response.status === 404) {
                    throw new Error('Transaksi tidak ditemukan atau berada di sekolah lain.');
                }

                throw new Error(backendError?.message || 'Detail transaksi gagal dimuat.');
            }

            if (!isDetailResponse(payload)) {
                throw new Error('Respons detail transaksi tidak valid.');
            }

            setSelectedDetail(payload.pickup_event);
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            setDetailError(error instanceof Error ? error.message : 'Detail transaksi gagal dimuat.');
        } finally {
            if (detailAbortRef.current === abortController) {
                detailAbortRef.current = null;

                setIsLoadingDetail(false);
            }
        }
    }

    function closeDetail(): void {
        if (isCancelling) {
            return;
        }

        detailAbortRef.current?.abort();

        detailAbortRef.current = null;

        setIsLoadingDetail(false);
        setSelectedDetail(null);
        setDetailError(null);
    }

    function openEventCancellation(event: PickupEventDetail): void {
        if (!event.can_cancel) {
            return;
        }

        setCancellationTarget({
            type: 'event',
            eventId: event.id,
            title: `Batalkan transaksi #${event.id}`,
        });

        setCancellationReason('');
        setActionError(null);
        setActionMessage(null);
    }

    function openStudentCancellation(event: PickupEventDetail, student: PickupEventDetailStudent): void {
        if (!student.can_cancel) {
            return;
        }

        setCancellationTarget({
            type: 'student',
            eventId: event.id,
            eventStudentId: student.id,

            title: `Batalkan penyerahan ${student.student_name}`,
        });

        setCancellationReason('');
        setActionError(null);
        setActionMessage(null);
    }

    function closeCancellationDialog(): void {
        if (isCancelling) {
            return;
        }

        cancellationAbortRef.current?.abort();

        cancellationAbortRef.current = null;

        setCancellationTarget(null);
        setCancellationReason('');
        setActionError(null);
    }

    async function submitCancellation(): Promise<void> {
        if (!cancellationTarget || isCancelling) {
            return;
        }

        const normalizedReason = cancellationReason.trim();

        if (normalizedReason.length < 5) {
            setActionError('Alasan pembatalan minimal 5 karakter.');

            return;
        }

        const url =
            cancellationTarget.type === 'event'
                ? `/gate/pickup-events/${cancellationTarget.eventId}/cancel`
                : `/gate/pickup-events/${cancellationTarget.eventId}/students/${cancellationTarget.eventStudentId}/cancel`;

        const abortController = new AbortController();

        cancellationAbortRef.current?.abort();

        cancellationAbortRef.current = abortController;

        setIsCancelling(true);
        setActionError(null);
        setActionMessage(null);

        try {
            const response = await fetch(url, {
                method: 'PATCH',

                credentials: 'same-origin',

                signal: abortController.signal,

                headers: {
                    Accept: 'application/json',

                    'Content-Type': 'application/json',

                    'X-Requested-With': 'XMLHttpRequest',

                    ...csrfHeaders(),
                },

                body: JSON.stringify({
                    reason: normalizedReason,
                }),
            });

            const payload = await readResponsePayload(response);

            const backendError = errorPayload(payload);

            if (!response.ok) {
                if (response.status === 401 || response.status === 419) {
                    throw new Error('Sesi login telah berakhir. Muat ulang halaman lalu masuk kembali.');
                }

                if (response.status === 403) {
                    throw new Error(backendError?.message || 'Akun tidak memiliki izin melakukan pembatalan.');
                }

                if (response.status === 404) {
                    throw new Error('Transaksi atau siswa tidak ditemukan.');
                }

                if (response.status === 409) {
                    throw new Error(backendError?.message || 'Data sudah dibatalkan atau tidak dapat diubah.');
                }

                if (response.status === 429) {
                    throw new Error(backendError?.message || 'Terlalu banyak permintaan pembatalan.');
                }

                const messages = validationMessages(backendError);

                throw new Error(messages[0] || backendError?.message || 'Pembatalan gagal diproses.');
            }

            if (!isMutationResponse(payload)) {
                throw new Error('Respons pembatalan dari backend tidak valid.');
            }

            setSelectedDetail(payload.pickup_event);

            setActionMessage(payload.message);

            setCancellationTarget(null);
            setCancellationReason('');

            router.reload({
                only: ['pickupEvents', 'summary'],
            });
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            setActionError(error instanceof Error ? error.message : 'Pembatalan gagal diproses.');
        } finally {
            if (cancellationAbortRef.current === abortController) {
                cancellationAbortRef.current = null;

                setIsCancelling(false);
            }
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Riwayat Gerbang" />

            <div className="module-page module-gate-history flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="module-history-hero flex flex-col gap-4 rounded-[28px] p-6 sm:flex-row sm:items-center sm:justify-between md:p-8">
                    <div>
                        <p className="text-muted-foreground text-sm">Keamanan Penjemputan</p>

                        <h1 className="text-2xl font-bold tracking-tight">Riwayat Gerbang</h1>

                        <p className="text-muted-foreground mt-2 max-w-3xl text-sm">
                            Lihat transaksi penjemputan, petugas yang mengonfirmasi, siswa yang diserahkan, dan riwayat pembatalannya.
                        </p>
                    </div>

                    <Link
                        href="/gate/face-verification"
                        className="module-primary-button inline-flex h-11 items-center justify-center rounded-xl px-5 text-sm font-bold text-white"
                    >
                        Verifikasi Penjemput
                    </Link>
                </header>

                {actionMessage && (
                    <div
                        role="status"
                        aria-live="polite"
                        className="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300"
                    >
                        {actionMessage}
                    </div>
                )}

                {actionError && !cancellationTarget && (
                    <div
                        role="alert"
                        aria-live="assertive"
                        className="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                    >
                        {actionError}
                    </div>
                )}

                <section>
                    <div className="mb-3">
                        <h2 className="font-semibold">Ringkasan Riwayat</h2>

                        <p className="text-muted-foreground mt-1 text-xs">Statistik mengikuti filter yang sedang aktif.</p>
                    </div>

                    <div className="module-history-summary-grid grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <SummaryCard label="Total Transaksi" value={summary.total_transactions} description="Seluruh hasil filter" />

                        <SummaryCard label="Dikonfirmasi" value={summary.confirmed_transactions} description="Transaksi aktif" tone="green" />

                        <SummaryCard label="Dibatalkan" value={summary.cancelled_transactions} description="Transaksi dibatalkan" tone="red" />

                        <SummaryCard label="Siswa Diserahkan" value={summary.released_students} description="Status penyerahan aktif" tone="blue" />

                        <SummaryCard label="Siswa Dibatalkan" value={summary.cancelled_students} description="Penyerahan dibatalkan" tone="amber" />
                    </div>
                </section>

                <section className="module-filter-panel module-history-filter rounded-[24px] p-4 md:p-5">
                    <form onSubmit={applyFilters} className="grid gap-4 lg:grid-cols-12">
                        <div className="lg:col-span-4">
                            <label htmlFor="history-search" className="text-sm font-medium">
                                Pencarian
                            </label>

                            <input
                                id="history-search"
                                type="search"
                                value={search}
                                onChange={(event) => {
                                    setSearch(event.target.value);
                                }}
                                maxLength={100}
                                placeholder="Nama penjemput, siswa, nomor..."
                                className="bg-background focus:border-primary mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none"
                            />
                        </div>

                        <div className="lg:col-span-2">
                            <label htmlFor="date-from" className="text-sm font-medium">
                                Tanggal Awal
                            </label>

                            <input
                                id="date-from"
                                type="date"
                                value={dateFrom}
                                max={dateTo || undefined}
                                onChange={(event) => {
                                    setDateFrom(event.target.value);
                                }}
                                className="bg-background focus:border-primary mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none"
                            />
                        </div>

                        <div className="lg:col-span-2">
                            <label htmlFor="date-to" className="text-sm font-medium">
                                Tanggal Akhir
                            </label>

                            <input
                                id="date-to"
                                type="date"
                                value={dateTo}
                                min={dateFrom || undefined}
                                onChange={(event) => {
                                    setDateTo(event.target.value);
                                }}
                                className="bg-background focus:border-primary mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none"
                            />
                        </div>

                        <div className="lg:col-span-2">
                            <label htmlFor="event-status" className="text-sm font-medium">
                                Status
                            </label>

                            <select
                                id="event-status"
                                value={status}
                                onChange={(event) => {
                                    setStatus(event.target.value);
                                }}
                                className="bg-background focus:border-primary mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none"
                            >
                                <option value="">Semua Status</option>

                                {filterOptions.statuses.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="lg:col-span-2">
                            <label htmlFor="verification-method" className="text-sm font-medium">
                                Metode
                            </label>

                            <select
                                id="verification-method"
                                value={verificationMethod}
                                onChange={(event) => {
                                    setVerificationMethod(event.target.value);
                                }}
                                className="bg-background focus:border-primary mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none"
                            >
                                <option value="">Semua Metode</option>

                                {filterOptions.verification_methods.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="lg:col-span-4">
                            <label htmlFor="officer" className="text-sm font-medium">
                                Petugas
                            </label>

                            <select
                                id="officer"
                                value={officerId}
                                onChange={(event) => {
                                    setOfficerId(event.target.value);
                                }}
                                className="bg-background focus:border-primary mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none"
                            >
                                <option value="">Semua Petugas</option>

                                {filterOptions.officers.map((officer) => (
                                    <option key={officer.id} value={officer.id}>
                                        {officer.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="lg:col-span-2">
                            <label htmlFor="per-page" className="text-sm font-medium">
                                Per Halaman
                            </label>

                            <select
                                id="per-page"
                                value={perPage}
                                onChange={(event) => {
                                    setPerPage(event.target.value);
                                }}
                                className="bg-background focus:border-primary mt-2 h-10 w-full rounded-md border px-3 text-sm outline-none"
                            >
                                {filterOptions.per_page_options.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex items-end gap-2 lg:col-span-6">
                            <button
                                type="submit"
                                className="module-filter-apply inline-flex h-11 flex-1 items-center justify-center rounded-xl px-4 text-sm font-bold text-white"
                            >
                                Terapkan Filter
                            </button>

                            <button
                                type="button"
                                onClick={resetFilters}
                                className="module-secondary-button inline-flex h-11 flex-1 items-center justify-center rounded-xl border px-4 text-sm font-bold"
                            >
                                Reset
                            </button>
                        </div>
                    </form>
                </section>

                <section className="module-table-panel module-history-table overflow-hidden rounded-[24px]">
                    <div className="border-b px-4 py-4 md:px-5">
                        <h2 className="font-semibold">Transaksi Penjemputan</h2>

                        <p className="text-muted-foreground mt-1 text-sm">
                            Menampilkan {pickupEvents.from ?? 0}–{pickupEvents.to ?? 0} dari {pickupEvents.total} transaksi.
                        </p>
                    </div>

                    {pickupEvents.data.length === 0 ? (
                        <div className="module-empty-state module-history-empty px-5 py-16 text-center">
                            <h3 className="font-semibold">Riwayat tidak ditemukan</h3>

                            <p className="text-muted-foreground mt-2 text-sm">Belum ada transaksi atau tidak ada data yang cocok dengan filter.</p>
                        </div>
                    ) : (
                        <>
                            <div className="hidden overflow-x-auto lg:block">
                                <table className="w-full min-w-[1000px] text-left text-sm">
                                    <thead className="bg-muted/40 text-muted-foreground border-b text-xs uppercase">
                                        <tr>
                                            <th className="px-5 py-3">Transaksi</th>

                                            <th className="px-5 py-3">Penjemput</th>

                                            <th className="px-5 py-3">Siswa</th>

                                            <th className="px-5 py-3">Petugas</th>

                                            <th className="px-5 py-3">Status</th>

                                            <th className="px-5 py-3 text-right">Aksi</th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y">
                                        {pickupEvents.data.map((item) => (
                                            <tr key={item.id} className="module-table-row module-history-row">
                                                <td className="px-5 py-4">
                                                    <p className="font-semibold">#{item.id}</p>

                                                    <p className="text-muted-foreground mt-1 text-xs">{formatDateTime(item.confirmed_at)}</p>

                                                    <p className="text-muted-foreground mt-1 text-xs">{item.verification_method_label}</p>
                                                </td>

                                                <td className="px-5 py-4">
                                                    <p className="font-medium">{item.pickup_person_name}</p>

                                                    <p className="text-muted-foreground mt-1 text-xs">{item.pickup_person_phone || '-'}</p>
                                                </td>

                                                <td className="px-5 py-4">
                                                    <p className="font-medium">{item.released_student_count} diserahkan</p>

                                                    {item.cancelled_student_count > 0 && (
                                                        <p className="mt-1 text-xs text-red-600">{item.cancelled_student_count} dibatalkan</p>
                                                    )}

                                                    <p className="text-muted-foreground mt-1 text-xs">Total {item.student_count} siswa</p>
                                                </td>

                                                <td className="px-5 py-4">{item.confirmed_by?.name || '-'}</td>

                                                <td className="px-5 py-4">
                                                    <StatusBadge status={item.status} label={item.status_label} />
                                                </td>

                                                <td className="px-5 py-4 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            void openDetail(item.id);
                                                        }}
                                                        className="module-detail-button inline-flex h-9 items-center justify-center rounded-xl border px-3 text-sm font-bold"
                                                    >
                                                        Detail
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="divide-y lg:hidden">
                                {pickupEvents.data.map((item) => (
                                    <article key={item.id} className="module-history-mobile-card p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-semibold">Transaksi #{item.id}</p>

                                                <p className="text-muted-foreground mt-1 text-xs">{formatDateTime(item.confirmed_at)}</p>
                                            </div>

                                            <StatusBadge status={item.status} label={item.status_label} />
                                        </div>

                                        <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                            <div>
                                                <dt className="text-muted-foreground text-xs">Penjemput</dt>

                                                <dd className="mt-1 font-medium">{item.pickup_person_name}</dd>
                                            </div>

                                            <div>
                                                <dt className="text-muted-foreground text-xs">Petugas</dt>

                                                <dd className="mt-1 font-medium">{item.confirmed_by?.name || '-'}</dd>
                                            </div>

                                            <div>
                                                <dt className="text-muted-foreground text-xs">Metode</dt>

                                                <dd className="mt-1 font-medium">{item.verification_method_label}</dd>
                                            </div>

                                            <div>
                                                <dt className="text-muted-foreground text-xs">Siswa</dt>

                                                <dd className="mt-1 font-medium">{item.released_student_count} diserahkan</dd>
                                            </div>
                                        </dl>

                                        <button
                                            type="button"
                                            onClick={() => {
                                                void openDetail(item.id);
                                            }}
                                            className="module-detail-button mt-4 inline-flex h-10 w-full items-center justify-center rounded-xl border px-4 text-sm font-bold"
                                        >
                                            Lihat Detail
                                        </button>
                                    </article>
                                ))}
                            </div>
                        </>
                    )}

                    {pickupEvents.links.length > 3 && (
                        <nav aria-label="Navigasi halaman riwayat" className="flex flex-wrap items-center justify-center gap-2 border-t px-4 py-4">
                            {pickupEvents.links.map((link, index) => {
                                const label = paginationLabel(link.label);

                                const key = `${link.url ?? label}-${index}`;

                                if (!link.url) {
                                    return (
                                        <span
                                            key={key}
                                            className="text-muted-foreground inline-flex h-9 min-w-9 cursor-not-allowed items-center justify-center rounded-md border px-3 text-sm opacity-50"
                                        >
                                            {label}
                                        </span>
                                    );
                                }

                                return (
                                    <Link
                                        key={key}
                                        href={link.url}
                                        preserveScroll
                                        aria-current={link.active ? 'page' : undefined}
                                        className={`inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm font-medium ${
                                            link.active ? 'border-primary bg-primary text-primary-foreground' : 'bg-background hover:bg-muted'
                                        }`}
                                    >
                                        {label}
                                    </Link>
                                );
                            })}
                        </nav>
                    )}
                </section>
            </div>

            <DetailModal
                open={detailModalOpen}
                loading={isLoadingDetail}
                error={detailError}
                detail={selectedDetail}
                actionMessage={actionMessage}
                isCancelling={isCancelling}
                cancellationDialogOpen={cancellationTarget !== null}
                onClose={closeDetail}
                onCancelEvent={openEventCancellation}
                onCancelStudent={openStudentCancellation}
            />

            <CancellationDialog
                target={cancellationTarget}
                reason={cancellationReason}
                error={actionError}
                busy={isCancelling}
                onReasonChange={setCancellationReason}
                onClose={closeCancellationDialog}
                onSubmit={() => {
                    void submitCancellation();
                }}
            />
        </AppLayout>
    );
}
