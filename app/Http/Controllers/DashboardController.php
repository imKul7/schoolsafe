<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PickupEvent;
use App\Models\PickupPerson;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
    ): Response {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $schoolId = $user->school_id;

        if ($schoolId === null) {
            return Inertia::render(
                'dashboard',
                [
                    'dashboard' => $this->emptyDashboard(),
                ],
            );
        }

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

        $timezone =
            $this->resolveTimezone(
                $school->timezone,
            );

        $storageTimezone =
            $this->resolveTimezone(
                (string) config(
                    'app.timezone',
                    'UTC',
                ),
            );

        $todayStartLocal =
            CarbonImmutable::now($timezone)
                ->startOfDay();

        $todayStart =
            $todayStartLocal
                ->setTimezone(
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

                    'recent_activities' => $this->recentActivities(
                        $schoolId,
                    ),
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
        $identifiers =
            timezone_identifiers_list();

        $candidate =
            trim(
                (string) $timezone,
            );

        if (
            $candidate !== ''
            && in_array(
                $candidate,
                $identifiers,
                true,
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
            $fallback !== ''
            && in_array(
                $fallback,
                $identifiers,
                true,
            )
        ) {
            return $fallback;
        }

        return 'UTC';
    }

    /**
     * @return array{
     *     has_school: bool,
     *     timezone: string,
     *     statistics: array{
     *         active_students: int,
     *         active_pickup_persons: int,
     *         registered_faces: int,
     *         pickup_events_today: int,
     *         confirmed_today: int,
     *         cancelled_today: int
     *     },
     *     recent_activities: list<array<string, mixed>>
     * }
     */
    private function emptyDashboard(): array
    {
        return [
            'has_school' => false,

            'timezone' => $this->resolveTimezone(
                (string) config(
                    'app.timezone',
                    'UTC',
                ),
            ),

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
