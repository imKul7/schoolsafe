<?php

declare(strict_types=1);

use App\Models\PickupEvent;
use App\Models\PickupPerson;
use App\Models\PickupPersonFaceVerificationAttempt;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function dashboardExistingAttributes(
    string $table,
    array $attributes,
): array {
    $columns =
        array_flip(
            Schema::getColumnListing($table),
        );

    return array_intersect_key(
        $attributes,
        $columns,
    );
}

function dashboardStudentGender(): string
{
    if (! Schema::hasColumn('students', 'gender')) {
        return 'L';
    }

    if (
        DB::connection()->getDriverName()
        !== 'mysql'
    ) {
        return 'L';
    }

    $column =
        DB::selectOne(
            <<<'SQL'
                SELECT COLUMN_TYPE AS column_type
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = 'students'
                  AND COLUMN_NAME = 'gender'
                LIMIT 1
            SQL,
            [
                DB::connection()->getDatabaseName(),
            ],
        );

    $columnType =
        (string) (
            $column?->column_type
            ?? ''
        );

    if (
        preg_match(
            "/^enum\\('([^']+)'/i",
            $columnType,
            $matches,
        ) === 1
    ) {
        return $matches[1];
    }

    return 'L';
}

function createDashboardSchoolClass(
    School $school,
): ?int {
    if (! Schema::hasTable('school_classes')) {
        return null;
    }

    $suffix =
        Str::upper(
            Str::random(8),
        );

    return (int) DB::table(
        'school_classes',
    )->insertGetId(
        dashboardExistingAttributes(
            'school_classes',
            [
                'school_id' => $school->id,
                'name' => "Kelas Dashboard {$suffix}",
                'class_name' => "Kelas Dashboard {$suffix}",
                'code' => "DASH-{$suffix}",
                'grade_level' => 5,
                'academic_year' => '2026/2027',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ),
    );
}

function createDashboardStudent(
    School $school,
    string $status,
): Student {
    $suffix =
        Str::lower(
            Str::random(12),
        );

    $schoolClassId =
        createDashboardSchoolClass(
            $school,
        );

    $attributes = [
        'school_id' => $school->id,
        'full_name' => "Siswa Dashboard {$suffix}",
        'student_number' => "DASH-{$suffix}",
        'nisn' => (string) random_int(
            1000000000,
            9999999999,
        ),
        'gender' => dashboardStudentGender(),
        'date_of_birth' => now()
            ->subYears(10)
            ->toDateString(),
        'birth_date' => now()
            ->subYears(10)
            ->toDateString(),
        'address' => 'Alamat siswa dashboard',
        'status' => $status,
    ];

    if ($schoolClassId !== null) {
        $attributes['school_class_id'] =
            $schoolClassId;

        $attributes['class_id'] =
            $schoolClassId;
    }

    $student = new Student;

    $student->forceFill(
        dashboardExistingAttributes(
            'students',
            $attributes,
        ),
    );

    $student->save();

    return $student->refresh();
}

function createDashboardPickupPerson(
    School $school,
    bool $isActive,
    string $faceStatus,
): PickupPerson {
    $suffix =
        Str::lower(
            Str::random(12),
        );

    $pickupPerson =
        new PickupPerson;

    $pickupPerson->forceFill(
        dashboardExistingAttributes(
            'pickup_persons',
            [
                'school_id' => $school->id,
                'full_name' => "Penjemput Dashboard {$suffix}",
                'identity_number' => "ID-{$suffix}",
                'phone' => '08'
                    .random_int(
                        1000000000,
                        9999999999,
                    ),
                'email' => "pickup-{$suffix}@schoolsafe.test",
                'address' => 'Alamat penjemput dashboard',
                'face_status' => $faceStatus,
                'is_active' => $isActive,
            ],
        ),
    );

    $pickupPerson->save();

    return $pickupPerson->refresh();
}

function createDashboardPickupEvent(
    School $school,
    User $confirmedBy,
    CarbonImmutable $confirmedAt,
    string $status,
): PickupEvent {
    $isCancelled =
        $status
        === PickupEvent::STATUS_CANCELLED;

    $event = new PickupEvent;

    $event->forceFill([
        'school_id' => $school->id,
        'pickup_person_id' => null,
        'face_verification_attempt_id' => null,
        'confirmed_by_user_id' => $confirmedBy->id,
        'cancelled_by_user_id' => $isCancelled
                ? $confirmedBy->id
                : null,
        'idempotency_key' => (string) Str::uuid(),
        'verification_method' => PickupEvent::VERIFICATION_METHOD_MANUAL,
        'status' => $status,
        'pickup_person_name' => 'Penjemput Dashboard',
        'pickup_person_phone' => '080000000000',
        'verification_result' => PickupPersonFaceVerificationAttempt::RESULT_MATCH,
        'similarity_score' => null,
        'similarity_threshold' => null,
        'candidate_margin' => null,
        'confirmed_at' => $confirmedAt,
        'cancelled_at' => $isCancelled
                ? $confirmedAt->addMinutes(5)
                : null,
        'cancellation_reason' => $isCancelled
                ? 'Pembatalan fixture dashboard'
                : null,
        'notes' => 'Fixture pengujian dashboard',
        'metadata' => [
            'source' => 'dashboard_test',
        ],
    ]);

    $event->save();

    return $event->refresh();
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow(
        CarbonImmutable::parse(
            '2026-07-30 09:00:00',
            'Asia/Jakarta',
        ),
    );
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

test(
    'dashboard statistics only contain authenticated school data',
    function (): void {
        $schoolA =
            School::factory()->create();

        $schoolB =
            School::factory()->create();

        $adminA =
            User::factory()
                ->schoolAdmin()
                ->create([
                    'school_id' => $schoolA->id,
                ]);

        $adminB =
            User::factory()
                ->schoolAdmin()
                ->create([
                    'school_id' => $schoolB->id,
                ]);

        createDashboardStudent(
            $schoolA,
            Student::STATUS_ACTIVE,
        );

        createDashboardStudent(
            $schoolA,
            Student::STATUS_ACTIVE,
        );

        createDashboardStudent(
            $schoolA,
            Student::STATUS_INACTIVE,
        );

        createDashboardStudent(
            $schoolB,
            Student::STATUS_ACTIVE,
        );

        createDashboardPickupPerson(
            $schoolA,
            true,
            PickupPerson::FACE_REGISTERED,
        );

        createDashboardPickupPerson(
            $schoolA,
            true,
            PickupPerson::FACE_NOT_REGISTERED,
        );

        createDashboardPickupPerson(
            $schoolA,
            false,
            PickupPerson::FACE_REGISTERED,
        );

        createDashboardPickupPerson(
            $schoolB,
            true,
            PickupPerson::FACE_REGISTERED,
        );

        $now =
            CarbonImmutable::now(
                'Asia/Jakarta',
            );

        createDashboardPickupEvent(
            $schoolA,
            $adminA,
            $now->subHour(),
            PickupEvent::STATUS_CONFIRMED,
        );

        createDashboardPickupEvent(
            $schoolA,
            $adminA,
            $now->subHours(2),
            PickupEvent::STATUS_CANCELLED,
        );

        createDashboardPickupEvent(
            $schoolA,
            $adminA,
            $now->subDay(),
            PickupEvent::STATUS_CONFIRMED,
        );

        createDashboardPickupEvent(
            $schoolB,
            $adminB,
            $now->subMinutes(30),
            PickupEvent::STATUS_CONFIRMED,
        );

        $this
            ->actingAs($adminA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('dashboard')
                    ->where(
                        'dashboard.has_school',
                        true,
                    )
                    ->where(
                        'dashboard.statistics.active_students',
                        2,
                    )
                    ->where(
                        'dashboard.statistics.active_pickup_persons',
                        2,
                    )
                    ->where(
                        'dashboard.statistics.registered_faces',
                        1,
                    )
                    ->where(
                        'dashboard.statistics.pickup_events_today',
                        2,
                    )
                    ->where(
                        'dashboard.statistics.confirmed_today',
                        1,
                    )
                    ->where(
                        'dashboard.statistics.cancelled_today',
                        1,
                    ),
            );
    },
);

test(
    'super admin dashboard does not aggregate every school',
    function (): void {
        $school =
            School::factory()->create();

        createDashboardPickupPerson(
            $school,
            true,
            PickupPerson::FACE_REGISTERED,
        );

        $superAdmin =
            User::factory()
                ->superAdmin()
                ->create();

        $this
            ->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page): Assert => $page
                    ->component('dashboard')
                    ->where(
                        'dashboard.has_school',
                        false,
                    )
                    ->where(
                        'dashboard.statistics',
                        [
                            'active_students' => 0,
                            'active_pickup_persons' => 0,
                            'registered_faces' => 0,
                            'pickup_events_today' => 0,
                            'confirmed_today' => 0,
                            'cancelled_today' => 0,
                        ],
                    ),
            );
    },
);

test(
    'guest is redirected before dashboard statistics are loaded',
    function (): void {
        $this
            ->get(route('dashboard'))
            ->assertRedirect(
                route('login'),
            );
    },
);
