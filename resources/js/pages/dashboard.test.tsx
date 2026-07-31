import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { AnchorHTMLAttributes, ComponentProps, PropsWithChildren } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import Dashboard from './dashboard';

const { reloadMock } = vi.hoisted(() => ({
    reloadMock: vi.fn(),
}));

type MockLinkProps = AnchorHTMLAttributes<HTMLAnchorElement> & {
    href: string;
    prefetch?: boolean;
};

vi.mock('@inertiajs/react', () => ({
    Head: () => null,

    Link: ({ children, href }: MockLinkProps) => <a href={href}>{children}</a>,

    router: {
        reload: reloadMock,
    },
}));

vi.mock('@/layouts/app-layout', () => ({
    default: ({ children }: PropsWithChildren) => <div data-testid="app-layout">{children}</div>,
}));

type DashboardProps = ComponentProps<typeof Dashboard>;

type DashboardData = DashboardProps['dashboard'];

type DashboardOverrides = Partial<Omit<DashboardData, 'permissions' | 'statistics' | 'recent_activities'>> & {
    permissions?: Partial<DashboardData['permissions']>;

    statistics?: Partial<DashboardData['statistics']>;

    recent_activities?: DashboardData['recent_activities'];
};

const baseDashboard: DashboardData = {
    has_school: true,

    timezone: 'Asia/Jakarta',

    generated_at: '2026-07-31T08:00:00Z',

    permissions: {
        can_open_face_scanner: true,

        can_view_pickup_history: true,

        can_view_gate_activity: true,
    },

    statistics: {
        active_students: 1234,

        active_pickup_persons: 20,

        registered_faces: 15,

        pickup_events_today: 10,

        confirmed_today: 8,

        cancelled_today: 2,
    },

    recent_activities: [
        {
            id: 100,

            pickup_person_name: 'Ratna Putri',

            status: 'confirmed',

            verification_method: 'face',

            confirmed_at: '2026-07-31T07:30:00Z',

            student_count: 2,
        },
    ],
};

function renderDashboard(overrides: DashboardOverrides = {}) {
    const dashboard: DashboardData = {
        ...baseDashboard,
        ...overrides,

        permissions: {
            ...baseDashboard.permissions,
            ...overrides.permissions,
        },

        statistics: {
            ...baseDashboard.statistics,
            ...overrides.statistics,
        },

        recent_activities: overrides.recent_activities ?? baseDashboard.recent_activities,
    };

    return render(<Dashboard dashboard={dashboard} />);
}

describe('Dashboard', () => {
    beforeEach(() => {
        reloadMock.mockReset();
    });

    it('renders tenant statistics and authorized gate actions', () => {
        renderDashboard();

        expect(
            screen.getByRole('heading', {
                name: 'Selamat datang di SchoolSafe 👋',
            }),
        ).toBeInTheDocument();

        const studentsCard = screen.getByText('Siswa Aktif').closest('article');

        expect(studentsCard).not.toBeNull();

        expect(within(studentsCard!).getByText('1.234')).toBeInTheDocument();

        expect(
            screen.getByRole('link', {
                name: 'Face Scanner',
            }),
        ).toHaveAttribute('href', '/gate/face-verification');

        expect(
            screen.getByRole('link', {
                name: 'Lihat seluruh riwayat',
            }),
        ).toHaveAttribute('href', '/gate/pickup-events');

        expect(
            screen.getByRole('heading', {
                name: 'Aktivitas Terbaru',
            }),
        ).toBeInTheDocument();

        expect(screen.getByText('Ratna Putri')).toBeInTheDocument();

        expect(
            screen.getByRole('progressbar', {
                name: 'Persentase wajah penjemput terdaftar',
            }),
        ).toHaveAttribute('aria-valuenow', '75');
    });

    it('hides gate-only content when permissions are disabled', () => {
        renderDashboard({
            permissions: {
                can_open_face_scanner: false,

                can_view_pickup_history: false,

                can_view_gate_activity: false,
            },
        });

        expect(
            screen.queryByRole('link', {
                name: 'Face Scanner',
            }),
        ).not.toBeInTheDocument();

        expect(
            screen.queryByRole('link', {
                name: 'Lihat seluruh riwayat',
            }),
        ).not.toBeInTheDocument();

        expect(
            screen.queryByRole('heading', {
                name: 'Aktivitas Terbaru',
            }),
        ).not.toBeInTheDocument();
    });

    it('reloads only dashboard data from the refresh action', async () => {
        reloadMock.mockImplementation(
            (options: {
                onStart?: () => void;

                onSuccess?: () => void;

                onError?: () => void;

                onFinish?: () => void;
            }) => {
                options.onStart?.();

                options.onSuccess?.();

                options.onFinish?.();
            },
        );

        const user = userEvent.setup();

        renderDashboard();

        const refreshButton = screen.getByRole('button', {
            name: 'Perbarui data dashboard',
        });

        await user.click(refreshButton);

        expect(reloadMock).toHaveBeenCalledTimes(1);

        expect(reloadMock).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['dashboard'],

                onStart: expect.any(Function),

                onSuccess: expect.any(Function),

                onError: expect.any(Function),

                onFinish: expect.any(Function),
            }),
        );

        await waitFor(() => {
            expect(refreshButton).toBeEnabled();
        });

        expect(screen.getByText('Data dashboard berhasil diperbarui.')).toBeInTheDocument();
    });

    it('shows an empty activity state when there are no recent events', () => {
        renderDashboard({
            recent_activities: [],
        });

        expect(screen.getByText('Belum ada aktivitas penjemputan')).toBeInTheDocument();

        expect(screen.getByText('Transaksi terbaru akan muncul di bagian ini.')).toBeInTheDocument();
    });
});
