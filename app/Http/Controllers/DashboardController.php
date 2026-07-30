<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PickupEvent;
use App\Models\PickupPerson;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
    ): Response {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        if ($user->school_id === null) {
            return Inertia::render(
                'dashboard',
                [
                    'dashboard' => $this->emptyDashboard(),
                ],
            );
        }

        $schoolId =
            (int) $user->school_id;

        $school =
            School::query()
                ->select([
                    'id',
                    'timezone',
                ])
                ->find($schoolId);

        if (! $school instanceof School) {
            return Inertia::render(
                'dashboard',
                [
                    'dashboard' => $this->emptyDashboard(),
                ],
            );
        }

        $canAccessGate =
            $user->hasRole(
                User::ROLE_SCHOOL_ADMIN,
                User::ROLE_GATE_OFFICER,
            );

        $timezone =
            $this->resolveTimezone(
                $school->timezone,
            );

        /*
         * Waktu penyimpanan database mengikuti timezone aplikasi.
         * Batas awal dan akhir hari dihitung menggunakan timezone
         * sekolah, lalu dikonversi ke timezone penyimpanan.
         */
        $storageTimezone =
            $this->resolveTimezone(
                (string) config(
                    'app.timezone',
                    'UTC',
                ),
            );

        $todayStartLocal =
            CarbonImmutable::now(
                $timezone,
            )->startOfDay();

        $todayStart =
            $todayStartLocal->setTimezone(
                $storageTimezone,
            );

        $tomorrowStart =
            $todayStartLocal
                ->addDay()
                ->setTimezone(
                    $storageTimezone,
                );

        $activePickupPeopleQuery =
            PickupPerson::query()
                ->where(
                    'school_id',
                    $schoolId,
                )
                ->where(
                    'is_active',
                    true,
                );

        $todayEventsQuery =
            PickupEvent::query()
                ->where(
                    'school_id',
                    $schoolId,
                )
                ->where(
                    'confirmed_at',
                    '>=',
                    $todayStart,
                )
                ->where(
                    'confirmed_at',
                    '<',
                    $tomorrowStart,
                );

        return Inertia::render(
            'dashboard',
            [
                'dashboard' => [
                    'has_school' => true,

                    'timezone' => $timezone,

                    'generated_at' => CarbonImmutable::now(
                        $storageTimezone,
                    )->toIso8601String(),

                    'permissions' => [
                    'can_open_face_scanner' => $canAccessGate,

                    'can_view_pickup_history' => $canAccessGate,

                    'can_view_gate_activity' => $canAccessGate,
                    ],

                    'statistics' => [
                    'active_students' => Student::query()
                        ->where(
                            'school_id',
                            $schoolId,
                        )
                        ->where(
                            'status',
                            Student::STATUS_ACTIVE,
                        )
                        ->count(),

                    'active_pickup_persons' => (clone $activePickupPeopleQuery)
                        ->count(),

                    'registered_faces' => (clone $activePickupPeopleQuery)
                        ->where(
                            'face_status',
                            PickupPerson::FACE_REGISTERED,
                        )
                        ->count(),

                    'pickup_events_today' => (clone $todayEventsQuery)
                        ->count(),

                    'confirmed_today' => (clone $todayEventsQuery)
                        ->where(
                            'status',
                            PickupEvent::STATUS_CONFIRMED,
                        )
                        ->count(),

                    'cancelled_today' => (clone $todayEventsQuery)
                        ->where(
                            'status',
                            PickupEvent::STATUS_CANCELLED,
                        )
                        ->count(),
                    ],

                    /*
                     * Nama penjemput dan aktivitas gerbang hanya
                     * diberikan kepada role yang memiliki akses
                     * terhadap modul gerbang.
                     */
                    'recent_activities' => $canAccessGate
                            ? $this->recentActivities(
                                $schoolId,
                            )
                            : [],
                ],
            ],
        );
    }

    /**
     * @return list<array{
     *     id: int,
     *     pickup_person_name: string,
     *     status: string,
     *     verification_method: string,
     *     confirmed_at: string|null,
     *     student_count: int
     * }>
     */
    private function recentActivities(
        int $schoolId,
    ): array {
        return PickupEvent::query()
            ->select([
                'id',
                'school_id',
                'pickup_person_name',
                'status',
                'verification_method',
                'confirmed_at',
            ])
            ->withCount(
                'eventStudents',
            )
            ->where(
                'school_id',
                $schoolId,
            )
            ->orderByDesc(
                'confirmed_at',
            )
            ->orderByDesc(
                'id',
            )
            ->limit(5)
            ->get()
            ->map(
                static fn (
                    PickupEvent $event,
                ): array => [
                    'id' => (int) $event->id,

                    'pickup_person_name' => (string) $event
                        ->pickup_person_name,

                    'status' => (string) $event->status,

                    'verification_method' => (string) $event
                        ->verification_method,

                    'confirmed_at' => $event
                        ->confirmed_at
                        ?->toIso8601String(),

                    'student_count' => (int) $event->getAttribute(
                        'event_students_count',
                    ),
                ],
            )
            ->values()
            ->all();
    }

    private function resolveTimezone(
        ?string $timezone,
    ): string {
        $candidate =
            trim(
                (string) $timezone,
            );

        if (
            $this->isValidTimezone(
                $candidate,
            )
        ) {
            return $candidate;
        }

        $fallback =
            trim(
                (string) config(
                    'app.timezone',
                    'UTC',
                ),
            );

        if (
            $this->isValidTimezone(
                $fallback,
            )
        ) {
            return $fallback;
        }

        return 'UTC';
    }

    private function isValidTimezone(
        string $timezone,
    ): bool {
        if ($timezone === '') {
            return false;
        }

        try {
            new DateTimeZone(
                $timezone,
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{
     *     has_school: bool,
     *     timezone: string,
     *     generated_at: string,
     *     permissions: array{
     *         can_open_face_scanner: bool,
     *         can_view_pickup_history: bool,
     *         can_view_gate_activity: bool
     *     },
     *     statistics: array{
     *         active_students: int,
     *         active_pickup_persons: int,
     *         registered_faces: int,
     *         pickup_events_today: int,
     *         confirmed_today: int,
     *         cancelled_today: int
     *     },
     *     recent_activities: list<array{
     *         id: int,
     *         pickup_person_name: string,
     *         status: string,
     *         verification_method: string,
     *         confirmed_at: string|null,
     *         student_count: int
     *     }>
     * }
     */
    private function emptyDashboard(): array
    {
        $timezone =
    $this->resolveTimezone(
        (string) config(
            'app.timezone',
            'UTC',
        ),
    );

        return [
            'has_school' => false,

            'timezone' => $timezone,

            'generated_at' => CarbonImmutable::now(
                $timezone,
            )->toIso8601String(),

            'permissions' => [
            'can_open_face_scanner' => false,
            'can_view_pickup_history' => false,
            'can_view_gate_activity' => false,
    ],

            'statistics' => [
            'active_students' => 0,
            'active_pickup_persons' => 0,
            'registered_faces' => 0,
            'pickup_events_today' => 0,
            'confirmed_today' => 0,
            'cancelled_today' => 0,
    ],

            'recent_activities' => [],
        ];
    }
}
