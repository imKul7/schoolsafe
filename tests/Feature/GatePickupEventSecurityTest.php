<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PickupEvent;
use App\Models\PickupEventStudent;
use App\Models\PickupPerson;
use App\Models\PickupPersonFaceVerificationAttempt;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

class GatePickupEventSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private School $schoolA;

    private School $schoolB;

    private User $adminA;

    private User $officerA;

    private User $officerB;

    private User $officerTenantB;

    private PickupPerson $pickupPersonA;

    private PickupPerson $pickupPersonB;

    private Student $studentA;

    private Student $studentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerSessionProbeRoute();

        $this->guardTestingDatabase();
        $this->assertRequiredTablesExist();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-07-21 10:00:00',
                'Asia/Jakarta',
            ),
        );

        /*
         * Session binding diuji pada test terpisah.
         *
         * Test ini berfokus pada tenant isolation,
         * replay, idempotency, stale attempt,
         * dan authorization pembatalan.
         */
        config()->set(
            'biometrics.security.bind_pickup_confirmation_to_session',
            false,
        );

        config()->set(
            'biometrics.security.pickup_confirmation_window_seconds',
            300,
        );

        config()->set(
            'biometrics.security.gate_cancellation_window_seconds',
            900,
        );

        config()->set(
            'biometrics.audit.store_ip',
            false,
        );

        config()->set(
            'biometrics.audit.store_ip_address',
            false,
        );

        config()->set(
            'biometrics.audit.store_user_agent',
            false,
        );

        $this->schoolA =
            $this->createSchool(
                'Tenant A',
            );

        $this->schoolB =
            $this->createSchool(
                'Tenant B',
            );

        $this->adminA =
            $this->createUser(
                $this->schoolA,
                User::ROLE_SCHOOL_ADMIN,
                'admin-a',
            );

        $this->officerA =
            $this->createUser(
                $this->schoolA,
                User::ROLE_GATE_OFFICER,
                'officer-a',
            );

        $this->officerB =
            $this->createUser(
                $this->schoolA,
                User::ROLE_GATE_OFFICER,
                'officer-b',
            );

        $this->officerTenantB =
            $this->createUser(
                $this->schoolB,
                User::ROLE_GATE_OFFICER,
                'officer-tenant-b',
            );

        $this->pickupPersonA =
            $this->createPickupPerson(
                $this->schoolA,
            );

        $this->studentA =
            $this->createStudent(
                $this->schoolA,
            );

        $this->authorizePickupPersonForStudent(
            $this->schoolA,
            $this->pickupPersonA,
            $this->studentA,
        );

        /*
        |--------------------------------------------------------------------------
        | Fixture tenant B
        |--------------------------------------------------------------------------
        |
        | Fixture master tenant B wajib terpisah dari tenant A agar pengujian
        | history benar-benar memverifikasi isolasi sekolah pada event, siswa,
        | penjemput, petugas, serta summary.
        |
        */

        $this->pickupPersonB =
            $this->createPickupPerson(
                $this->schoolB,
            );

        $this->studentB =
            $this->createStudent(
                $this->schoolB,
            );

        $this->authorizePickupPersonForStudent(
            $this->schoolB,
            $this->pickupPersonB,
            $this->studentB,
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Replay idempotency
    |--------------------------------------------------------------------------
    */

    public function test_same_request_is_replayed_without_creating_second_event(): void
    {
        $attempt =
            $this->createMatchAttempt(
                $this->schoolA,
                $this->officerA,
                $this->pickupPersonA,
            );

        $payload =
            $this->confirmationPayload(
                $attempt,
                (string) Str::uuid(),
                'Konfirmasi otomatis',
            );

        /*
         * actingAs dipanggil satu kali agar kedua request
         * memakai session yang sama.
         */
        $this->actingAs(
            $this->officerA,
        );

        $firstResponse =
            $this->postJson(
                '/gate/pickup-events',
                $payload,
            );

        $firstResponse
            ->assertCreated()
            ->assertJsonPath(
                'replayed',
                false,
            );

        $eventId =
            (int) $firstResponse->json(
                'pickup_event.id',
            );

        $this->postJson(
            '/gate/pickup-events',
            $payload,
        )
            ->assertOk()
            ->assertJsonPath(
                'replayed',
                true,
            )
            ->assertJsonPath(
                'pickup_event.id',
                $eventId,
            );

        $this->assertSame(
            1,
            PickupEvent::query()
                ->where(
                    'idempotency_key',
                    $payload[
                        'idempotency_key'
                    ],
                )
                ->count(),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Idempotency conflict
    |--------------------------------------------------------------------------
    */

    public function test_same_idempotency_key_with_different_payload_is_rejected(): void
    {
        $attempt =
            $this->createMatchAttempt(
                $this->schoolA,
                $this->officerA,
                $this->pickupPersonA,
            );

        $idempotencyKey =
            (string) Str::uuid();

        $this->actingAs(
            $this->officerA,
        );

        $this->postJson(
            '/gate/pickup-events',
            $this->confirmationPayload(
                $attempt,
                $idempotencyKey,
                'Catatan pertama',
            ),
        )->assertCreated();

        $this->postJson(
            '/gate/pickup-events',
            $this->confirmationPayload(
                $attempt,
                $idempotencyKey,
                'Catatan diubah',
            ),
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'Kunci idempotency sudah digunakan untuk request yang berbeda.',
            );

        $this->assertSame(
            1,
            PickupEvent::query()
                ->where(
                    'idempotency_key',
                    $idempotencyKey,
                )
                ->count(),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Attempt replay dengan UUID baru
    |--------------------------------------------------------------------------
    */

    public function test_used_attempt_cannot_be_reused_with_new_idempotency_key(): void
    {
        $attempt =
            $this->createMatchAttempt(
                $this->schoolA,
                $this->officerA,
                $this->pickupPersonA,
            );

        $this->actingAs(
            $this->officerA,
        );

        $this->postJson(
            '/gate/pickup-events',
            $this->confirmationPayload(
                $attempt,
                (string) Str::uuid(),
                'Konfirmasi pertama',
            ),
        )->assertCreated();

        $this->postJson(
            '/gate/pickup-events',
            $this->confirmationPayload(
                $attempt,
                (string) Str::uuid(),
                'Konfirmasi kedua',
            ),
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'Hasil verifikasi wajah ini sudah digunakan untuk transaksi penjemputan.',
            );

        $this->assertSame(
            1,
            PickupEvent::query()
                ->where(
                    'face_verification_attempt_id',
                    $attempt->id,
                )
                ->count(),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Attempt kedaluwarsa
    |--------------------------------------------------------------------------
    */

    public function test_stale_attempt_is_rejected_and_does_not_create_event(): void
    {
        $attempt =
            $this->createMatchAttempt(
                $this->schoolA,
                $this->officerA,
                $this->pickupPersonA,
                now()->subMinutes(10),
            );

        $idempotencyKey =
            (string) Str::uuid();

        $this->actingAs(
            $this->officerA,
        )
            ->postJson(
                '/gate/pickup-events',
                $this->confirmationPayload(
                    $attempt,
                    $idempotencyKey,
                    'Attempt kedaluwarsa',
                ),
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'face_verification_attempt_id',
            ]);

        $this->assertDatabaseMissing(
            'pickup_events',
            [
                'idempotency_key' =>
                    $idempotencyKey,
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Tenant isolation detail
    |--------------------------------------------------------------------------
    */

    public function test_other_tenant_cannot_view_event_detail(): void
    {
        $tenantBEvent =
            $this->createConfirmedEvent(
                $this->schoolB,
                $this->officerTenantB,
                now(),
            );

        $this->actingAs(
            $this->officerA,
        )
            ->getJson(
                "/gate/pickup-events/{$tenantBEvent->id}",
            )
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Tenant isolation cancellation
    |--------------------------------------------------------------------------
    */

    public function test_other_tenant_cannot_cancel_event(): void
    {
        $tenantBEvent =
            $this->createConfirmedEvent(
                $this->schoolB,
                $this->officerTenantB,
                now(),
            );

        $this->actingAs(
            $this->officerA,
        )
            ->patchJson(
                "/gate/pickup-events/{$tenantBEvent->id}/cancel",
                [
                    'reason' =>
                        'Percobaan lintas tenant',
                ],
            )
            ->assertNotFound();

        $this->assertDatabaseHas(
            'pickup_events',
            [
                'id' =>
                    $tenantBEvent->id,

                'status' =>
                    PickupEvent::STATUS_CONFIRMED,
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Petugas lain tidak boleh membatalkan
    |--------------------------------------------------------------------------
    */

    public function test_gate_officer_cannot_cancel_event_confirmed_by_another_officer(): void
    {
        $event =
            $this->createConfirmedEvent(
                $this->schoolA,
                $this->officerA,
                now(),
            );

        $this->actingAs(
            $this->officerB,
        )
            ->patchJson(
                "/gate/pickup-events/{$event->id}/cancel",
                [
                    'reason' =>
                        'Dibatalkan petugas lain',
                ],
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'pickup_events',
            [
                'id' =>
                    $event->id,

                'status' =>
                    PickupEvent::STATUS_CONFIRMED,
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Petugas pembuat dapat membatalkan dalam batas waktu
    |--------------------------------------------------------------------------
    */

    public function test_gate_officer_can_cancel_own_event_inside_window(): void
    {
        $event =
            $this->createConfirmedEvent(
                $this->schoolA,
                $this->officerA,
                now()->subMinutes(5),
                true,
            );

        $this->actingAs(
            $this->officerA,
        )
            ->patchJson(
                "/gate/pickup-events/{$event->id}/cancel",
                [
                    'reason' =>
                        'Kesalahan operasional gerbang',
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'pickup_event.status',
                PickupEvent::STATUS_CANCELLED,
            );

        $this->assertDatabaseHas(
            'pickup_events',
            [
                'id' =>
                    $event->id,

                'status' =>
                    PickupEvent::STATUS_CANCELLED,

                'cancelled_by_user_id' =>
                    $this->officerA->id,
            ],
        );

        $this->assertSame(
            0,
            PickupEventStudent::query()
                ->where(
                    'pickup_event_id',
                    $event->id,
                )
                ->where(
                    'status',
                    PickupEventStudent::STATUS_RELEASED,
                )
                ->count(),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Petugas pembuat tidak dapat melewati batas waktu
    |--------------------------------------------------------------------------
    */

    public function test_gate_officer_cannot_cancel_own_event_after_window(): void
    {
        $event =
            $this->createConfirmedEvent(
                $this->schoolA,
                $this->officerA,
                now()->subHour(),
            );

        $this->actingAs(
            $this->officerA,
        )
            ->patchJson(
                "/gate/pickup-events/{$event->id}/cancel",
                [
                    'reason' =>
                        'Pembatalan melewati batas waktu',
                ],
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'pickup_events',
            [
                'id' =>
                    $event->id,

                'status' =>
                    PickupEvent::STATUS_CONFIRMED,
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Administrator dapat melakukan koreksi administratif
    |--------------------------------------------------------------------------
    */

    public function test_school_admin_can_cancel_old_event_in_same_tenant(): void
    {
        $event =
            $this->createConfirmedEvent(
                $this->schoolA,
                $this->officerA,
                now()->subDay(),
            );

        $this->actingAs(
            $this->adminA,
        )
            ->patchJson(
                "/gate/pickup-events/{$event->id}/cancel",
                [
                    'reason' =>
                        'Koreksi administratif sekolah',
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'pickup_event.status',
                PickupEvent::STATUS_CANCELLED,
            );

        $this->assertDatabaseHas(
            'pickup_events',
            [
                'id' =>
                    $event->id,

                'status' =>
                    PickupEvent::STATUS_CANCELLED,

                'cancelled_by_user_id' =>
                    $this->adminA->id,
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pembatalan siswa mengikuti authorization parent
    |--------------------------------------------------------------------------
    */

    public function test_other_officer_cannot_cancel_student_from_another_officers_event(): void
    {
        $event =
            $this->createConfirmedEvent(
                $this->schoolA,
                $this->officerA,
                now(),
                true,
            );

        $eventStudent =
            PickupEventStudent::query()
                ->where(
                    'pickup_event_id',
                    $event->id,
                )
                ->firstOrFail();

        $this->actingAs(
            $this->officerB,
        )
            ->patchJson(
                "/gate/pickup-events/{$event->id}/students/{$eventStudent->id}/cancel",
                [
                    'reason' =>
                        'Pembatalan siswa oleh petugas lain',
                ],
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'pickup_event_students',
            [
                'id' =>
                    $eventStudent->id,

                'status' =>
                    PickupEventStudent::STATUS_RELEASED,
            ],
        );
    }

    /*
|--------------------------------------------------------------------------
| Session binding: sesi yang sama
|--------------------------------------------------------------------------
*/

public function test_same_session_can_confirm_and_replay_session_bound_attempt(): void
{
    config()->set(
        'biometrics.security.bind_pickup_confirmation_to_session',
        true,
    );

    $browserSession =
        $this->establishAuthenticatedBrowserSession(
            $this->officerA,
        );

    $attempt =
        $this->createMatchAttempt(
            school:
                $this->schoolA,

            verifiedBy:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            metadata: [
                'security' => [
                    'session_binding' =>
                        $this->sessionBindingForTest(
                            sessionId:
                                $browserSession[
                                    'session_id'
                                ],

                            school:
                                $this->schoolA,

                            user:
                                $this->officerA,
                        ),

                    'verified_by_user_id' =>
                        (int) $this
                            ->officerA
                            ->id,

                    'school_id' =>
                        (int) $this
                            ->schoolA
                            ->id,
                ],
            ],
        );

    $payload =
        $this->confirmationPayload(
            $attempt,
            (string) Str::uuid(),
            'Konfirmasi session binding',
        );

    $firstResponse =
    $this
        ->withCredentials()
        ->withUnencryptedCookie(
            $browserSession[
                'cookie_name'
            ],
            $browserSession[
                'cookie_value'
            ],
        )
        ->postJson(
            '/gate/pickup-events',
            $payload,
        );

    $firstResponse
        ->assertCreated()
        ->assertJsonPath(
            'replayed',
            false,
        );

    $eventId =
        (int) $firstResponse->json(
            'pickup_event.id',
        );

    $this
    ->withCredentials()
    ->withUnencryptedCookie(
        $browserSession[
            'cookie_name'
        ],
        $browserSession[
            'cookie_value'
        ],
    )
    ->postJson(
        '/gate/pickup-events',
        $payload,
    )
        ->assertOk()
        ->assertJsonPath(
            'replayed',
            true,
        )
        ->assertJsonPath(
            'pickup_event.id',
            $eventId,
        );

    $this->assertSame(
        1,
        PickupEvent::query()
            ->where(
                'face_verification_attempt_id',
                $attempt->id,
            )
            ->count(),
    );
}

/*
|--------------------------------------------------------------------------
| Session binding: sesi berbeda
|--------------------------------------------------------------------------
*/

public function test_attempt_bound_to_different_session_is_rejected(): void
{
    config()->set(
        'biometrics.security.bind_pickup_confirmation_to_session',
        true,
    );

    $originalSession =
        $this->establishAuthenticatedBrowserSession(
            $this->officerA,
        );

    $attempt =
        $this->createMatchAttempt(
            school:
                $this->schoolA,

            verifiedBy:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            metadata: [
                'security' => [
                    'session_binding' =>
                        $this->sessionBindingForTest(
                            sessionId:
                                $originalSession[
                                    'session_id'
                                ],

                            school:
                                $this->schoolA,

                            user:
                                $this->officerA,
                        ),

                    'verified_by_user_id' =>
                        (int) $this
                            ->officerA
                            ->id,

                    'school_id' =>
                        (int) $this
                            ->schoolA
                            ->id,
                ],
            ],
        );

    /*
     * Regenerasi menghasilkan ID dan cookie session baru.
     * Attempt tetap terikat pada session sebelumnya.
     */
    $differentSession =
        $this->regenerateBrowserSession(
            $originalSession,
        );

    $this->assertNotSame(
        $originalSession[
            'session_id'
        ],
        $differentSession[
            'session_id'
        ],
    );

    $idempotencyKey =
        (string) Str::uuid();

    $this
    ->withCredentials()
    ->withUnencryptedCookie(
        $differentSession[
            'cookie_name'
        ],
        $differentSession[
            'cookie_value'
        ],
    )
    ->postJson(
            '/gate/pickup-events',
            $this->confirmationPayload(
                $attempt,
                $idempotencyKey,
                'Percobaan sesi berbeda',
            ),
        )
        ->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'Hasil verifikasi berasal dari sesi yang berbeda. Jalankan verifikasi wajah ulang.',
        )
        ->assertJsonValidationErrors([
            'face_verification_attempt_id',
        ]);

    $this->assertDatabaseMissing(
        'pickup_events',
        [
            'idempotency_key' =>
                $idempotencyKey,
        ],
    );
}
    
    /*
    |--------------------------------------------------------------------------
    | Confirmation payload
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{
     *     idempotency_key: string,
     *     face_verification_attempt_id: int,
     *     student_ids: array<int>,
     *     notes: string
     * }
     */
    
    public function test_pickup_event_keeps_audit_snapshot_after_source_records_change(): void
{
    config()->set(
        'biometrics.security.bind_pickup_confirmation_to_session',
        false,
    );

    $attempt =
        $this->createMatchAttempt(
            school:
                $this->schoolA,

            verifiedBy:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,
        );

    $response =
        $this
            ->actingAs(
                $this->officerA,
            )
            ->postJson(
                '/gate/pickup-events',
                $this->confirmationPayload(
                    $attempt,
                    (string) Str::uuid(),
                    'Audit snapshot test',
                ),
            );

    $response
        ->assertCreated()
        ->assertJsonPath(
            'replayed',
            false,
        );

    $eventId =
        (int) $response->json(
            'pickup_event.id',
        );

    $originalPickupPersonName =
        (string) $response->json(
            'pickup_event.pickup_person.full_name',
        );

    $originalPickupPersonPhone =
        $response->json(
            'pickup_event.pickup_person.phone',
        );

    $originalStudentName =
        (string) $response->json(
            'pickup_event.students.0.student_name',
        );

    $originalStudentNumber =
        $response->json(
            'pickup_event.students.0.student_number',
        );

    $originalIsPrimary =
        (bool) $response->json(
            'pickup_event.students.0.is_primary',
        );

    /*
     * Ubah data master setelah transaksi dikonfirmasi.
     */
    $changedPickupPersonName =
        sprintf(
            'Changed Pickup %d',
            $this->pickupPersonA->id,
        );

    $changedPickupPersonPhone =
        '081299999999';

    $this->pickupPersonA->forceFill([
        'full_name' =>
            $changedPickupPersonName,

        'phone' =>
            $changedPickupPersonPhone,
    ])->save();

    $changedStudentName =
        sprintf(
            'Changed Student %d',
            $this->studentA->id,
        );

    $changedStudentNumber =
        sprintf(
            'MUT-%d',
            $this->studentA->id,
        );

    $this->studentA->forceFill([
        'full_name' =>
            $changedStudentName,

        'student_number' =>
            $changedStudentNumber,
    ])->save();

    /*
     * Ubah relasi penjemput-siswa. Snapshot transaksi lama tidak
     * boleh mengikuti perubahan pada pivot master.
     */
    DB::table(
        'pickup_person_student',
    )
        ->where(
            'school_id',
            $this->schoolA->id,
        )
        ->where(
            'pickup_person_id',
            $this->pickupPersonA->id,
        )
        ->where(
            'student_id',
            $this->studentA->id,
        )
        ->update([
            'is_primary' =>
                ! $originalIsPrimary,

            'updated_at' =>
                now(),
        ]);

    $detailResponse =
        $this
            ->actingAs(
                $this->officerA,
            )
            ->getJson(
                sprintf(
                    '/gate/pickup-events/%d',
                    $eventId,
                ),
            );

    $detailResponse
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.pickup_person.full_name',
            $originalPickupPersonName,
        )
        ->assertJsonPath(
            'pickup_event.pickup_person.phone',
            $originalPickupPersonPhone,
        )
        ->assertJsonPath(
            'pickup_event.students.0.student_name',
            $originalStudentName,
        )
        ->assertJsonPath(
            'pickup_event.students.0.student_number',
            $originalStudentNumber,
        )
        ->assertJsonPath(
            'pickup_event.students.0.is_primary',
            $originalIsPrimary,
        );

    /*
     * Pastikan test memang berhasil mengubah sumber datanya.
     */
    $this->assertSame(
        $changedPickupPersonName,
        (string) $this->pickupPersonA
            ->fresh()
            ?->full_name,
    );

    $this->assertSame(
        $changedStudentName,
        (string) $this->studentA
            ->fresh()
            ?->full_name,
    );

    /*
     * Snapshot histori tidak boleh berubah mengikuti master.
     */
    $this->assertNotSame(
        $changedPickupPersonName,
        $detailResponse->json(
            'pickup_event.pickup_person.full_name',
        ),
    );

    $this->assertNotSame(
        $changedStudentName,
        $detailResponse->json(
            'pickup_event.students.0.student_name',
        ),
    );
}

public function test_pickup_event_detail_response_has_strict_safe_contract(): void
{
    config()->set(
        'biometrics.security.bind_pickup_confirmation_to_session',
        false,
    );

    $attempt =
        $this->createMatchAttempt(
            school:
                $this->schoolA,

            verifiedBy:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            metadata: [
                'security' => [
                    'session_binding' =>
                        'must-not-be-exposed',

                    'request_fingerprint' =>
                        'must-not-be-exposed',
                ],

                'embedding' =>
                    [
                        0.11,
                        0.22,
                        0.33,
                    ],

                'private_audit_value' =>
                    'must-not-be-exposed',
            ],
        );

    $response =
        $this
            ->actingAs(
                $this->officerA,
            )
            ->postJson(
                '/gate/pickup-events',
                $this->confirmationPayload(
                    $attempt,
                    (string) Str::uuid(),
                    'Response contract test',
                ),
            );

    $response->assertCreated();

    $eventPayload =
        $response->json(
            'pickup_event',
        );

    $this->assertIsArray(
        $eventPayload,
    );

    $this->assertExactArrayKeys(
        payload:
            $eventPayload,

        expectedKeys: [
            'id',
            'idempotency_key',
            'status',
            'status_label',
            'verification_method',
            'verification_method_label',
            'verification_result',
            'similarity_score',
            'similarity_threshold',
            'candidate_margin',
            'confirmed_at',
            'cancelled_at',
            'cancellation_reason',
            'notes',
            'can_cancel',
            'pickup_person',
            'confirmed_by',
            'cancelled_by',
            'verification_attempt',
            'students',
        ],

        context:
            'pickup_event',
    );

    $pickupPerson =
        $eventPayload[
            'pickup_person'
        ] ?? null;

    $this->assertIsArray(
        $pickupPerson,
    );

    $this->assertExactArrayKeys(
        payload:
            $pickupPerson,

        expectedKeys: [
            'id',
            'full_name',
            'phone',
        ],

        context:
            'pickup_event.pickup_person',
    );

    $confirmedBy =
        $eventPayload[
            'confirmed_by'
        ] ?? null;

    $this->assertIsArray(
        $confirmedBy,
    );

    $this->assertExactArrayKeys(
        payload:
            $confirmedBy,

        expectedKeys: [
            'id',
            'name',
        ],

        context:
            'pickup_event.confirmed_by',
    );

    $verificationAttempt =
        $eventPayload[
            'verification_attempt'
        ] ?? null;

    $this->assertIsArray(
        $verificationAttempt,
    );

    $this->assertExactArrayKeys(
        payload:
            $verificationAttempt,

        expectedKeys: [
            'id',
            'result',
            'similarity_score',
            'similarity_threshold',
            'candidate_margin',
            'quality_score',
            'liveness_passed',
            'live_score',
            'real_score',
            'model_name',
            'model_version',
            'occurred_at',
        ],

        context:
            'pickup_event.verification_attempt',
    );

    $students =
        $eventPayload[
            'students'
        ] ?? null;

    $this->assertIsArray(
        $students,
    );

    $this->assertNotEmpty(
        $students,
    );

    $student =
        $students[0]
        ?? null;

    $this->assertIsArray(
        $student,
    );

    $this->assertExactArrayKeys(
        payload:
            $student,

        expectedKeys: [
            'id',
            'student_id',
            'student_name',
            'student_number',
            'class_name',
            'academic_year',
            'relationship_type',
            'is_primary',
            'status',
            'status_label',
            'released_at',
            'cancelled_at',
            'cancellation_reason',
            'cancelled_by',
            'can_cancel',
        ],

        context:
            'pickup_event.students.*',
    );

    /*
     * Pemeriksaan eksplisit data sensitif.
     */
    foreach (
        [
            'metadata',
            'ip_address',
            'user_agent',
            'request_fingerprint',
            'session_binding',
            'embedding',
            'embedding_vector',
            'captured_image',
            'captured_image_path',
        ] as $forbiddenKey
    ) {
        $this->assertArrayNotHasKey(
            $forbiddenKey,
            $eventPayload,
            sprintf(
                'Field sensitif [%s] tidak boleh ada pada pickup_event.',
                $forbiddenKey,
            ),
        );

        $this->assertArrayNotHasKey(
            $forbiddenKey,
            $verificationAttempt,
            sprintf(
                'Field sensitif [%s] tidak boleh ada pada verification_attempt.',
                $forbiddenKey,
            ),
        );
    }

    $encodedResponse =
        json_encode(
            $response->json(),
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE,
        );

    $this->assertStringNotContainsString(
        'must-not-be-exposed',
        $encodedResponse,
    );

    $this->assertStringNotContainsString(
        'private_audit_value',
        $encodedResponse,
    );
}    

public function test_history_only_contains_events_from_authenticated_users_school(): void
{
    $eventFromOwnSchool =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            pickupPersonName:
                'Tenant A Pickup Person',
        );

    /*
     * Event sekolah lain sengaja dibuat sebagai cancelled agar
     * summary lintas tenant juga dapat terdeteksi.
     */
    $this->createHistoryFixtureEvent(
        school:
            $this->schoolB,

        officer:
            $this->officerTenantB,

        pickupPerson:
            $this->pickupPersonB,

        student:
            $this->studentB,

        status:
            PickupEvent::STATUS_CANCELLED,

        pickupPersonName:
            'Tenant B Pickup Person',
    );

    $response =
        $this
            ->actingAs(
                $this->officerA,
            )
            ->get(
                '/gate/pickup-events?per_page=50',
            );

    $response
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page,
            ): Assert =>
                $page
                    ->component(
                        'gate/pickup-events/index',
                    )
                    ->has(
                        'pickupEvents.data',
                        1,
                    )
                    ->where(
                        'pickupEvents.data.0.id',
                        (int) $eventFromOwnSchool->id,
                    )
                    ->where(
                        'pickupEvents.total',
                        1,
                    )
                    ->where(
                        'summary.total_transactions',
                        1,
                    )
                    ->where(
                        'summary.confirmed_transactions',
                        1,
                    )
                    ->where(
                        'summary.cancelled_transactions',
                        0,
                    )
                    ->where(
                        'summary.released_students',
                        1,
                    )
                    ->where(
                        'summary.cancelled_students',
                        0,
                    ),
        );
}

public function test_history_date_filter_uses_school_timezone_boundaries(): void
{
    /*
     * Database dan batas query dipaksa menggunakan UTC.
     * Sekolah menggunakan Asia/Jakarta atau UTC+7.
     */
    config()->set(
        'app.timezone',
        'UTC',
    );

    $this->schoolA->forceFill([
        'timezone' =>
            'Asia/Jakarta',
    ])->save();

    /*
     * 20 Juli 2026 pukul 16:30 UTC
     * = 20 Juli 2026 pukul 23:30 WIB.
     *
     * Event ini tidak boleh masuk filter tanggal 21 Juli.
     */
    $outsideLocalDate =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::parse(
                    '2026-07-20 16:30:00',
                    'UTC',
                ),

            pickupPersonName:
                'Outside Local Date',
        );

    /*
     * 20 Juli 2026 pukul 17:30 UTC
     * = 21 Juli 2026 pukul 00:30 WIB.
     *
     * Event ini wajib masuk filter tanggal 21 Juli.
     */
    $insideLocalDate =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::parse(
                    '2026-07-20 17:30:00',
                    'UTC',
                ),

            pickupPersonName:
                'Inside Local Date',
        );

    $response =
        $this
            ->actingAs(
                $this->officerA,
            )
            ->get(
                '/gate/pickup-events'
                .'?date_from=2026-07-21'
                .'&date_to=2026-07-21'
                .'&per_page=50',
            );

    $response
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page,
            ): Assert =>
                $page
                    ->component(
                        'gate/pickup-events/index',
                    )
                    ->has(
                        'pickupEvents.data',
                        1,
                    )
                    ->where(
                        'pickupEvents.data.0.id',
                        (int) $insideLocalDate->id,
                    )
                    ->where(
                        'pickupEvents.total',
                        1,
                    )
                    ->where(
                        'summary.total_transactions',
                        1,
                    )
                    ->where(
                        'summary.confirmed_transactions',
                        1,
                    )
                    ->where(
                        'summary.cancelled_transactions',
                        0,
                    ),
        );

    $pageContent =
        (string) $response->getContent();

    $this->assertStringNotContainsString(
        sprintf(
            '"id":%d',
            $outsideLocalDate->id,
        ),
        $pageContent,
    );
}

public function test_history_combines_status_method_officer_and_search_filters(): void
{
    $this->createHistoryFixtureEvent(
        school:
            $this->schoolA,

        officer:
            $this->officerA,

        pickupPerson:
            $this->pickupPersonA,

        student:
            $this->studentA,

        verificationMethod:
            PickupEvent::VERIFICATION_METHOD_FACE,

        pickupPersonName:
            'History Face Alpha',
    );

    $manualEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            verificationMethod:
                PickupEvent::VERIFICATION_METHOD_MANUAL,

            pickupPersonName:
                'History Manual Beta',
        );

    $cancelledEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            status:
                PickupEvent::STATUS_CANCELLED,

            verificationMethod:
                PickupEvent::VERIFICATION_METHOD_FACE,

            pickupPersonName:
                'History Cancelled Gamma',
        );

    $searchTarget =
        sprintf(
            'History Search Target %s',
            Str::lower(
                Str::random(8),
            ),
        );

    $targetEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerB,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            status:
                PickupEvent::STATUS_CONFIRMED,

            verificationMethod:
                PickupEvent::VERIFICATION_METHOD_FACE,

            pickupPersonName:
                $searchTarget,
        );

    /*
     * Filter status.
     */
    $this
        ->actingAs(
            $this->officerA,
        )
        ->get(
            '/gate/pickup-events'
            .'?status='
            .PickupEvent::STATUS_CANCELLED
            .'&per_page=50',
        )
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page,
            ): Assert =>
                $page
                    ->has(
                        'pickupEvents.data',
                        1,
                    )
                    ->where(
                        'pickupEvents.data.0.id',
                        (int) $cancelledEvent->id,
                    )
                    ->where(
                        'summary.total_transactions',
                        1,
                    )
                    ->where(
                        'summary.confirmed_transactions',
                        0,
                    )
                    ->where(
                        'summary.cancelled_transactions',
                        1,
                    )
                    ->where(
                        'summary.released_students',
                        0,
                    )
                    ->where(
                        'summary.cancelled_students',
                        1,
                    ),
        );

    /*
     * Filter metode verifikasi.
     */
    $this
        ->get(
            '/gate/pickup-events'
            .'?verification_method='
            .PickupEvent::VERIFICATION_METHOD_MANUAL
            .'&per_page=50',
        )
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page,
            ): Assert =>
                $page
                    ->has(
                        'pickupEvents.data',
                        1,
                    )
                    ->where(
                        'pickupEvents.data.0.id',
                        (int) $manualEvent->id,
                    )
                    ->where(
                        'summary.total_transactions',
                        1,
                    )
                    ->where(
                        'summary.confirmed_transactions',
                        1,
                    )
                    ->where(
                        'summary.released_students',
                        1,
                    ),
        );

    /*
     * Filter petugas yang mengonfirmasi.
     */
    $this
        ->get(
            '/gate/pickup-events'
            .'?confirmed_by_user_id='
            .$this->officerB->id
            .'&per_page=50',
        )
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page,
            ): Assert =>
                $page
                    ->has(
                        'pickupEvents.data',
                        1,
                    )
                    ->where(
                        'pickupEvents.data.0.id',
                        (int) $targetEvent->id,
                    )
                    ->where(
                        'summary.total_transactions',
                        1,
                    ),
        );

    /*
     * Filter pencarian snapshot nama penjemput.
     */
    $this
        ->get(
            '/gate/pickup-events'
            .'?search='
            .rawurlencode(
                $searchTarget,
            )
            .'&per_page=50',
        )
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page,
            ): Assert =>
                $page
                    ->has(
                        'pickupEvents.data',
                        1,
                    )
                    ->where(
                        'pickupEvents.data.0.id',
                        (int) $targetEvent->id,
                    )
                    ->where(
                        'summary.total_transactions',
                        1,
                    ),
        );

    /*
     * Seluruh filter dikombinasikan. Hanya targetEvent yang cocok.
     */
    $combinedUrl =
        '/gate/pickup-events?'
        .http_build_query([
            'status' =>
                PickupEvent::STATUS_CONFIRMED,

            'verification_method' =>
                PickupEvent::VERIFICATION_METHOD_FACE,

            'confirmed_by_user_id' =>
                $this->officerB->id,

            'search' =>
                $searchTarget,

            'per_page' =>
                50,
        ]);

    $this
        ->get(
            $combinedUrl,
        )
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page,
            ): Assert =>
                $page
                    ->has(
                        'pickupEvents.data',
                        1,
                    )
                    ->where(
                        'pickupEvents.data.0.id',
                        (int) $targetEvent->id,
                    )
                    ->where(
                        'pickupEvents.total',
                        1,
                    )
                    ->where(
                        'summary.total_transactions',
                        1,
                    )
                    ->where(
                        'summary.confirmed_transactions',
                        1,
                    )
                    ->where(
                        'summary.cancelled_transactions',
                        0,
                    )
                    ->where(
                        'summary.released_students',
                        1,
                    )
                    ->where(
                        'summary.cancelled_students',
                        0,
                    ),
        );
}

public function test_history_rejects_invalid_status_method_and_per_page_filters(): void
{
    $response =
        $this
            ->actingAs(
                $this->officerA,
            )
            ->from(
                '/gate/pickup-events',
            )
            ->get(
                '/gate/pickup-events?'
                .http_build_query([
                    'status' =>
                        'invalid-status',

                    'verification_method' =>
                        'invalid-method',

                    'per_page' =>
                        999,
                ]),
            );

    $response
        ->assertRedirect(
            '/gate/pickup-events',
        )
        ->assertSessionHasErrors([
            'status',
            'verification_method',
            'per_page',
        ]);

    $this->assertSame(
        0,
        PickupEvent::query()
            ->where(
                'school_id',
                $this->schoolA->id,
            )
            ->count(),
    );
}

public function test_history_rejects_invalid_date_format(): void
{
    $response =
        $this
            ->actingAs(
                $this->officerA,
            )
            ->from(
                '/gate/pickup-events',
            )
            ->get(
                '/gate/pickup-events?'
                .http_build_query([
                    'date_from' =>
                        '21-07-2026',

                    'date_to' =>
                        'tanggal-tidak-valid',

                    'per_page' =>
                        10,
                ]),
            );

    $response
        ->assertRedirect(
            '/gate/pickup-events',
        )
        ->assertSessionHasErrors([
            'date_from',
            'date_to',
        ]);
}

public function test_history_rejects_date_to_before_date_from(): void
{
    $response =
        $this
            ->actingAs(
                $this->officerA,
            )
            ->from(
                '/gate/pickup-events',
            )
            ->get(
                '/gate/pickup-events?'
                .http_build_query([
                    'date_from' =>
                        '2026-07-22',

                    'date_to' =>
                        '2026-07-20',

                    'per_page' =>
                        10,
                ]),
            );

    $response
        ->assertRedirect(
            '/gate/pickup-events',
        )
        ->assertSessionHasErrors([
            'date_to',
        ]);
}

public function test_history_rejects_confirmed_by_user_from_other_tenant(): void
{
    /*
     * Pastikan fixture benar-benar berasal dari sekolah berbeda.
     */
    $this->assertNotSame(
        (int) $this->schoolA->id,
        (int) $this->officerTenantB->school_id,
    );

    $response =
        $this
            ->actingAs(
                $this->officerA,
            )
            ->from(
                '/gate/pickup-events',
            )
            ->get(
                '/gate/pickup-events?'
                .http_build_query([
                    'confirmed_by_user_id' =>
                        $this->officerTenantB->id,

                    'per_page' =>
                        10,
                ]),
            );

    $response
        ->assertRedirect(
            '/gate/pickup-events',
        )
        ->assertSessionHasErrors([
            'confirmed_by_user_id',
        ]);
}

public function test_history_trims_search_filter_and_uses_normalized_value_for_summary(): void
{
    $searchTarget =
        sprintf(
            'Normalized Search %s',
            Str::lower(
                Str::random(8),
            ),
        );

    $targetEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            pickupPersonName:
                $searchTarget,
        );

    /*
     * Event lain tidak boleh cocok dengan pencarian.
     */
    $this->createHistoryFixtureEvent(
        school:
            $this->schoolA,

        officer:
            $this->officerA,

        pickupPerson:
            $this->pickupPersonA,

        student:
            $this->studentA,

        pickupPersonName:
            'Unrelated Canonical Search',
    );

    $searchWithWhitespace =
        sprintf(
            '   %s   ',
            $searchTarget,
        );

    $response =
        $this
            ->actingAs(
                $this->officerA,
            )
            ->get(
                '/gate/pickup-events?'
                .http_build_query([
                    'search' =>
                        $searchWithWhitespace,

                    'per_page' =>
                        10,
                ]),
            );

    $response
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page,
            ): Assert =>
                $page
                    ->component(
                        'gate/pickup-events/index',
                    )
                    ->has(
                        'pickupEvents.data',
                        1,
                    )
                    ->where(
                        'pickupEvents.data.0.id',
                        (int) $targetEvent->id,
                    )
                    ->where(
                        'pickupEvents.total',
                        1,
                    )
                    ->where(
                        'summary.total_transactions',
                        1,
                    )
                    ->where(
                        'summary.confirmed_transactions',
                        1,
                    )
                    ->where(
                        'summary.cancelled_transactions',
                        0,
                    )
                    ->where(
                        'summary.released_students',
                        1,
                    )
                    ->where(
                        'summary.cancelled_students',
                        0,
                    )
                    ->where(
                        'filters.search',
                        $searchTarget,
                    )
                    ->where(
                        'filters.per_page',
                        10,
                    ),
        );
}

public function test_history_rejects_overlong_search_filter(): void
{
    $overlongSearch =
        str_repeat(
            'A',
            256,
        );

    $response =
        $this
            ->actingAs(
                $this->officerA,
            )
            ->from(
                '/gate/pickup-events',
            )
            ->get(
                '/gate/pickup-events?'
                .http_build_query([
                    'search' =>
                        $overlongSearch,

                    'per_page' =>
                        10,
                ]),
            );

    $response
        ->assertRedirect(
            '/gate/pickup-events',
        )
        ->assertSessionHasErrors([
            'search',
        ]);
}

public function test_history_item_response_has_strict_safe_contract(): void
{
    $searchTarget =
        sprintf(
            'History Safe Contract %s',
            Str::lower(
                Str::random(8),
            ),
        );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            pickupPersonName:
                $searchTarget,

            notes:
                'history-secret-notes',
        );

    /*
     * Data berikut memang disimpan pada database untuk audit internal,
     * tetapi tidak boleh dikirim pada payload daftar riwayat.
     */
    $event->forceFill([
        'ip_address' =>
            '203.0.113.10',

        'user_agent' =>
            'history-secret-user-agent',

        'metadata' => [
            'request_fingerprint' =>
                'history-secret-fingerprint',

            'session_binding' =>
                'history-secret-session-binding',

            'private_value' =>
                'history-secret-private-value',
        ],
    ])->save();

    $response =
        $this
            ->actingAs(
                $this->officerA,
            )
            ->get(
                '/gate/pickup-events?'
                .http_build_query([
                    'search' =>
                        $searchTarget,

                    'per_page' =>
                        10,
                ]),
            );

    $response
        ->assertOk()
        ->assertInertia(
            function (
                Assert $page,
            ) use (
                $event,
            ): Assert {
                return $page
                    ->component(
                        'gate/pickup-events/index',
                    )
                    ->has(
                        'pickupEvents.data',
                        1,
                    )
                    ->where(
                        'pickupEvents.data.0',
                        function (
                            mixed $payload,
                        ) use (
                            $event,
                        ): bool {
                            $payload =
                                $this->normalizeInertiaValue(
                                    $payload,
                                );

                            $this->assertIsArray(
                                $payload,
                            );

                            $this->assertExactArrayKeys(
                                payload:
                                    $payload,

                                expectedKeys: [
                                    'id',
                                    'status',
                                    'status_label',
                                    'verification_method',
                                    'verification_method_label',
                                    'pickup_person_name',
                                    'pickup_person_phone',
                                    'confirmed_at',
                                    'cancelled_at',
                                    'confirmed_by',
                                    'cancelled_by',
                                    'student_count',
                                    'released_student_count',
                                    'cancelled_student_count',
                                    'can_cancel',
                                    'url',
                                ],

                                context:
                                    'pickupEvents.data.*',
                            );

                            $this->assertSame(
                                (int) $event->id,
                                (int) $payload[
                                    'id'
                                ],
                            );

                            $this->assertSame(
                                (string) $event
                                    ->pickup_person_name,
                                (string) $payload[
                                    'pickup_person_name'
                                ],
                            );

                            $this->assertSame(
                                sprintf(
                                    '/gate/pickup-events/%d',
                                    $event->id,
                                ),
                                $payload[
                                    'url'
                                ],
                            );

                            $confirmedBy =
                                $payload[
                                    'confirmed_by'
                                ] ?? null;

                            $this->assertIsArray(
                                $confirmedBy,
                            );

                            $this->assertExactArrayKeys(
                                payload:
                                    $confirmedBy,

                                expectedKeys: [
                                    'id',
                                    'name',
                                ],

                                context:
                                    'pickupEvents.data.*.confirmed_by',
                            );

                            $this->assertSame(
                                (int) $this
                                    ->officerA
                                    ->id,
                                (int) $confirmedBy[
                                    'id'
                                ],
                            );

                            $this->assertNull(
                                $payload[
                                    'cancelled_by'
                                ],
                            );

                            $this->assertSame(
                                1,
                                (int) $payload[
                                    'student_count'
                                ],
                            );

                            $this->assertSame(
                                1,
                                (int) $payload[
                                    'released_student_count'
                                ],
                            );

                            $this->assertSame(
                                0,
                                (int) $payload[
                                    'cancelled_student_count'
                                ],
                            );

                            foreach (
                                [
                                    'school_id',
                                    'pickup_person_id',
                                    'face_verification_attempt_id',
                                    'confirmed_by_user_id',
                                    'cancelled_by_user_id',
                                    'idempotency_key',
                                    'verification_result',
                                    'similarity_score',
                                    'similarity_threshold',
                                    'candidate_margin',
                                    'cancellation_reason',
                                    'notes',
                                    'ip_address',
                                    'user_agent',
                                    'metadata',
                                    'request_fingerprint',
                                    'session_binding',
                                    'embedding',
                                    'created_at',
                                    'updated_at',
                                ] as $forbiddenKey
                            ) {
                                $this->assertArrayNotHasKey(
                                    $forbiddenKey,
                                    $payload,
                                    sprintf(
                                        'Field sensitif [%s] tidak boleh ada pada payload history.',
                                        $forbiddenKey,
                                    ),
                                );
                            }

                            return true;
                        },
                    );
            },
        );

    /*
     * Pemeriksaan tambahan terhadap seluruh serialized response.
     */
    $encodedResponse =
        (string) $response->getContent();

    foreach (
        [
            'history-secret-notes',
            '203.0.113.10',
            'history-secret-user-agent',
            'history-secret-fingerprint',
            'history-secret-session-binding',
            'history-secret-private-value',
        ] as $secretValue
    ) {
        $this->assertStringNotContainsString(
            $secretValue,
            $encodedResponse,
        );
    }
}

public function test_history_filter_options_only_include_active_same_tenant_authorized_users(): void
{
    $inactiveOfficer =
        $this->createUser(
            $this->schoolA,
            User::ROLE_GATE_OFFICER,
            'inactive-history-officer',
        );

    $inactiveOfficer->forceFill([
        'is_active' =>
            false,
    ])->save();

    $response =
        $this
            ->actingAs(
                $this->officerA,
            )
            ->get(
                '/gate/pickup-events?per_page=10',
            );

    $response
        ->assertOk()
        ->assertInertia(
            function (
                Assert $page,
            ) use (
                $inactiveOfficer,
            ): Assert {
                return $page
                    ->component(
                        'gate/pickup-events/index',
                    )
                    ->where(
                        'filterOptions',
                        function (
                            mixed $filterOptions,
                        ) use (
                            $inactiveOfficer,
                        ): bool {
                            $filterOptions =
                                $this->normalizeInertiaValue(
                                    $filterOptions,
                                );

                            $this->assertIsArray(
                                $filterOptions,
                            );

                            $this->assertExactArrayKeys(
                                payload:
                                    $filterOptions,

                                expectedKeys: [
                                    'statuses',
                                    'verification_methods',
                                    'officers',
                                    'per_page_options',
                                ],

                                context:
                                    'filterOptions',
                            );

                            $officers =
                                $filterOptions[
                                    'officers'
                                ] ?? null;

                            $this->assertIsArray(
                                $officers,
                            );

                            $expectedOfficers = [
                                [
                                    'id' =>
                                        (int) $this
                                            ->adminA
                                            ->id,

                                    'name' =>
                                        (string) $this
                                            ->adminA
                                            ->name,
                                ],
                                [
                                    'id' =>
                                        (int) $this
                                            ->officerA
                                            ->id,

                                    'name' =>
                                        (string) $this
                                            ->officerA
                                            ->name,
                                ],
                                [
                                    'id' =>
                                        (int) $this
                                            ->officerB
                                            ->id,

                                    'name' =>
                                        (string) $this
                                            ->officerB
                                            ->name,
                                ],
                            ];

                            usort(
                                $expectedOfficers,
                                static function (
                                    array $left,
                                    array $right,
                                ): int {
                                    $nameComparison =
                                        strcmp(
                                            (string) $left[
                                                'name'
                                            ],
                                            (string) $right[
                                                'name'
                                            ],
                                        );

                                    if ($nameComparison !== 0) {
                                        return $nameComparison;
                                    }

                                    return (
                                        (int) $left[
                                            'id'
                                        ]
                                    ) <=> (
                                        (int) $right[
                                            'id'
                                        ]
                                    );
                                },
                            );

                            $this->assertSame(
                                $expectedOfficers,
                                $officers,
                            );

                            foreach (
                                $officers as
                                $officerOption
                            ) {
                                $this->assertIsArray(
                                    $officerOption,
                                );

                                $this->assertExactArrayKeys(
                                    payload:
                                        $officerOption,

                                    expectedKeys: [
                                        'id',
                                        'name',
                                    ],

                                    context:
                                        'filterOptions.officers.*',
                                );
                            }

                            $officerIds =
                                array_map(
                                    static fn (
                                        array $officer,
                                    ): int =>
                                        (int) $officer[
                                            'id'
                                        ],
                                    $officers,
                                );

                            /*
                             * User dari tenant lain tidak boleh muncul.
                             */
                            $this->assertNotContains(
                                (int) $this
                                    ->officerTenantB
                                    ->id,
                                $officerIds,
                            );

                            /*
                             * User tidak aktif dari tenant yang sama
                             * juga tidak boleh muncul.
                             */
                            $this->assertNotContains(
                                (int) $inactiveOfficer
                                    ->id,
                                $officerIds,
                            );

                            $this->assertSame(
                                [
                                    10,
                                    15,
                                    25,
                                    50,
                                ],
                                $filterOptions[
                                    'per_page_options'
                                ],
                            );

                            $this->assertSame(
                                [
                                    [
                                        'value' =>
                                            PickupEvent::STATUS_CONFIRMED,

                                        'label' =>
                                            'Dikonfirmasi',
                                    ],
                                    [
                                        'value' =>
                                            PickupEvent::STATUS_CANCELLED,

                                        'label' =>
                                            'Dibatalkan',
                                    ],
                                ],
                                $filterOptions[
                                    'statuses'
                                ],
                            );

                            $this->assertSame(
                                [
                                    [
                                        'value' =>
                                            PickupEvent::VERIFICATION_METHOD_FACE,

                                        'label' =>
                                            'Verifikasi Wajah',
                                    ],
                                    [
                                        'value' =>
                                            PickupEvent::VERIFICATION_METHOD_MANUAL,

                                        'label' =>
                                            'Verifikasi Manual',
                                    ],
                                ],
                                $filterOptions[
                                    'verification_methods'
                                ],
                            );

                            return true;
                        },
                    );
            },
        );
}

public function test_history_order_is_deterministic_by_confirmed_at_and_id_descending(): void
{
    $searchToken =
        sprintf(
            'Deterministic History %s',
            Str::lower(
                Str::random(8),
            ),
        );

    $oldestEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::parse(
                    '2026-07-21 01:00:00',
                    'UTC',
                ),

            pickupPersonName:
                "{$searchToken} Oldest",
        );

    /*
     * Dua event berikut sengaja memiliki confirmed_at yang sama.
     * Event yang dibuat kemudian mempunyai ID lebih besar.
     */
    $firstTiedEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::parse(
                    '2026-07-21 02:00:00',
                    'UTC',
                ),

            pickupPersonName:
                "{$searchToken} First Tie",
        );

    $secondTiedEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::parse(
                    '2026-07-21 02:00:00',
                    'UTC',
                ),

            pickupPersonName:
                "{$searchToken} Second Tie",
        );

    $newestEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::parse(
                    '2026-07-21 03:00:00',
                    'UTC',
                ),

            pickupPersonName:
                "{$searchToken} Newest",
        );

    $expectedIds = [
        (int) $newestEvent->id,
        (int) $secondTiedEvent->id,
        (int) $firstTiedEvent->id,
        (int) $oldestEvent->id,
    ];

    $response =
        $this
            ->actingAs(
                $this->officerA,
            )
            ->get(
                '/gate/pickup-events?'
                .http_build_query([
                    'search' =>
                        $searchToken,

                    'per_page' =>
                        10,
                ]),
            );

    $response
        ->assertOk()
        ->assertInertia(
            function (
                Assert $page,
            ) use (
                $expectedIds,
            ): Assert {
                return $page
                    ->component(
                        'gate/pickup-events/index',
                    )
                    ->has(
                        'pickupEvents.data',
                        4,
                    )
                    ->where(
                        'pickupEvents.data',
                        function (
                            mixed $items,
                        ) use (
                            $expectedIds,
                        ): bool {
                            $items =
                                $this->normalizeInertiaValue(
                                    $items,
                                );

                            $this->assertIsArray(
                                $items,
                            );

                            $actualIds =
                                array_map(
                                    static fn (
                                        array $item,
                                    ): int =>
                                        (int) $item[
                                            'id'
                                        ],
                                    $items,
                                );

                            $this->assertSame(
                                $expectedIds,
                                $actualIds,
                                'History harus diurutkan berdasarkan confirmed_at DESC lalu id DESC.',
                            );

                            return true;
                        },
                    )
                    ->where(
                        'pickupEvents.total',
                        4,
                    )
                    ->where(
                        'summary.total_transactions',
                        4,
                    );
            },
        );
}

public function test_guest_cannot_access_gate_pickup_history_or_detail(): void
{
    /*
     * Request browser biasa diarahkan ke halaman login.
     */
    $this
        ->get(
            '/gate/pickup-events',
        )
        ->assertRedirect(
            '/login',
        );

    /*
     * Request JSON harus memperoleh status 401,
     * bukan data transaksi atau status 404.
     */
    $this
        ->getJson(
            '/gate/pickup-events/999999999',
        )
        ->assertUnauthorized();
}

public function test_inactive_gate_officer_cannot_access_history_detail_or_cancel(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            pickupPersonName:
                'Inactive Officer Security Event',
        );

    /*
     * Akun dinonaktifkan setelah fixture transaksi selesai dibuat.
     */
    $this->officerA->forceFill([
        'is_active' =>
            false,
    ])->save();

    $this
        ->actingAs(
            $this->officerA,
        )
        ->get(
            '/gate/pickup-events',
        )
        ->assertForbidden();

    $this
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun Anda sedang tidak aktif.',
        );

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Percobaan oleh akun tidak aktif',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun Anda sedang tidak aktif.',
        );

    /*
     * Penolakan authorization tidak boleh mengubah transaksi.
     */
    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $event->id,

            'status' =>
                PickupEvent::STATUS_CONFIRMED,

            'cancelled_at' =>
                null,

            'cancelled_by_user_id' =>
                null,
        ],
    );

    $this->assertDatabaseHas(
        'pickup_event_students',
        [
            'pickup_event_id' =>
                $event->id,

            'status' =>
                PickupEventStudent::STATUS_RELEASED,

            'cancelled_at' =>
                null,

            'cancelled_by_user_id' =>
                null,
        ],
    );
}

public function test_teacher_cannot_access_history_detail_or_cancel(): void
{
    $teacher =
        $this->createUser(
            $this->schoolA,
            'teacher',
            'teacher-gate-security',
        );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            pickupPersonName:
                'Teacher Authorization Security Event',
        );

    $this
        ->actingAs(
            $teacher,
        )
        ->get(
            '/gate/pickup-events',
        )
        ->assertForbidden();

    $this
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun tidak memiliki izin mengelola transaksi gerbang.',
        );

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Percobaan pembatalan oleh guru',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun tidak memiliki izin mengelola transaksi gerbang.',
        );

    /*
     * Transaksi dan siswa harus tetap berstatus semula.
     */
    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $event->id,

            'status' =>
                PickupEvent::STATUS_CONFIRMED,

            'cancelled_at' =>
                null,

            'cancelled_by_user_id' =>
                null,
        ],
    );

    $this->assertDatabaseHas(
        'pickup_event_students',
        [
            'pickup_event_id' =>
                $event->id,

            'status' =>
                PickupEventStudent::STATUS_RELEASED,

            'cancelled_at' =>
                null,

            'cancelled_by_user_id' =>
                null,
        ],
    );
}

public function test_school_admin_can_view_and_cancel_old_event_from_history(): void
{
    $searchTarget =
        sprintf(
            'Admin Historical Correction %s',
            Str::lower(
                Str::random(8),
            ),
        );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now()
                    ->subDays(7),

            pickupPersonName:
                $searchTarget,
        );

    /*
     * Administrator harus dapat melihat history dan memperoleh
     * can_cancel=true walaupun transaksi sudah berusia tujuh hari.
     */
    $this
        ->actingAs(
            $this->adminA,
        )
        ->get(
            '/gate/pickup-events?'
            .http_build_query([
                'search' =>
                    $searchTarget,

                'per_page' =>
                    10,
            ]),
        )
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page,
            ): Assert =>
                $page
                    ->component(
                        'gate/pickup-events/index',
                    )
                    ->has(
                        'pickupEvents.data',
                        1,
                    )
                    ->where(
                        'pickupEvents.data.0.id',
                        (int) $event->id,
                    )
                    ->where(
                        'pickupEvents.data.0.can_cancel',
                        true,
                    )
                    ->where(
                        'pickupEvents.total',
                        1,
                    ),
        );

    /*
     * Detail transaksi juga harus memberikan authorization
     * pembatalan kepada administrator.
     */
    $this
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.id',
            (int) $event->id,
        )
        ->assertJsonPath(
            'pickup_event.can_cancel',
            true,
        );

    /*
     * Administrator melakukan koreksi administratif.
     */
    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Koreksi administratif transaksi lama',
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CANCELLED,
        )
        ->assertJsonPath(
            'pickup_event.cancelled_by.id',
            (int) $this->adminA->id,
        );

    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $event->id,

            'status' =>
                PickupEvent::STATUS_CANCELLED,

            'cancelled_by_user_id' =>
                $this->adminA->id,

            'cancellation_reason' =>
                'Koreksi administratif transaksi lama',
        ],
    );

    $this->assertDatabaseHas(
        'pickup_event_students',
        [
            'pickup_event_id' =>
                $event->id,

            'status' =>
                PickupEventStudent::STATUS_CANCELLED,

            'cancelled_by_user_id' =>
                $this->adminA->id,

            'cancellation_reason' =>
                'Koreksi administratif transaksi lama',
        ],
    );
}

public function test_inactive_gate_officer_cannot_cancel_event_student(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            pickupPersonName:
                'Inactive Student Cancellation Event',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $this->officerA->forceFill([
        'is_active' =>
            false,
    ])->save();

    $this
        ->actingAs(
            $this->officerA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'Percobaan pembatalan siswa oleh akun tidak aktif',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun Anda sedang tidak aktif.',
        );

    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $event->id,

            'status' =>
                PickupEvent::STATUS_CONFIRMED,

            'cancelled_at' =>
                null,

            'cancelled_by_user_id' =>
                null,
        ],
    );

    $this->assertDatabaseHas(
        'pickup_event_students',
        [
            'id' =>
                $eventStudent->id,

            'pickup_event_id' =>
                $event->id,

            'status' =>
                PickupEventStudent::STATUS_RELEASED,

            'cancelled_at' =>
                null,

            'cancelled_by_user_id' =>
                null,
        ],
    );
}

public function test_teacher_cannot_cancel_event_student(): void
{
    $teacher =
        $this->createUser(
            $this->schoolA,
            'teacher',
            'teacher-student-cancellation',
        );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            pickupPersonName:
                'Teacher Student Cancellation Event',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $this
        ->actingAs(
            $teacher,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'Percobaan pembatalan siswa oleh guru',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun tidak memiliki izin mengelola transaksi gerbang.',
        );

    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $event->id,

            'status' =>
                PickupEvent::STATUS_CONFIRMED,

            'cancelled_at' =>
                null,

            'cancelled_by_user_id' =>
                null,
        ],
    );

    $this->assertDatabaseHas(
        'pickup_event_students',
        [
            'id' =>
                $eventStudent->id,

            'status' =>
                PickupEventStudent::STATUS_RELEASED,

            'cancelled_at' =>
                null,

            'cancelled_by_user_id' =>
                null,
        ],
    );
}

public function test_gate_officer_can_cancel_student_from_own_event_inside_window(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Own Student Cancellation Event',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $reason =
        'Koreksi penyerahan siswa oleh petugas terkait';

    $this
        ->actingAs(
            $this->officerA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    $reason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.id',
            (int) $event->id,
        )
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CANCELLED,
        )
        ->assertJsonPath(
            'pickup_event.cancelled_by.id',
            (int) $this->officerA->id,
        );

    /*
     * Fixture hanya memiliki satu siswa.
     * Setelah siswa terakhir dibatalkan, parent event juga
     * harus berubah menjadi cancelled.
     */
    $this->assertDatabaseHas(
        'pickup_event_students',
        [
            'id' =>
                $eventStudent->id,

            'pickup_event_id' =>
                $event->id,

            'status' =>
                PickupEventStudent::STATUS_CANCELLED,

            'cancelled_by_user_id' =>
                $this->officerA->id,

            'cancellation_reason' =>
                $reason,
        ],
    );

    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $event->id,

            'status' =>
                PickupEvent::STATUS_CANCELLED,

            'cancelled_by_user_id' =>
                $this->officerA->id,

            'cancellation_reason' =>
                $reason,
        ],
    );
}

public function test_school_admin_can_cancel_student_from_old_event(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now()
                    ->subDays(7),

            pickupPersonName:
                'Admin Old Student Cancellation Event',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $reason =
        'Koreksi administratif siswa pada transaksi lama';

    $this
        ->actingAs(
            $this->adminA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    $reason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.id',
            (int) $event->id,
        )
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CANCELLED,
        )
        ->assertJsonPath(
            'pickup_event.cancelled_by.id',
            (int) $this->adminA->id,
        );

    $this->assertDatabaseHas(
        'pickup_event_students',
        [
            'id' =>
                $eventStudent->id,

            'status' =>
                PickupEventStudent::STATUS_CANCELLED,

            'cancelled_by_user_id' =>
                $this->adminA->id,

            'cancellation_reason' =>
                $reason,
        ],
    );

    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $event->id,

            'status' =>
                PickupEvent::STATUS_CANCELLED,

            'cancelled_by_user_id' =>
                $this->adminA->id,

            'cancellation_reason' =>
                $reason,
        ],
    );
}

public function test_other_tenant_cannot_cancel_event_student_and_receives_not_found(): void
{
    $tenantBEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolB,

            officer:
                $this->officerTenantB,

            pickupPerson:
                $this->pickupPersonB,

            student:
                $this->studentB,

            pickupPersonName:
                'Tenant B Student Cancellation Event',
        );

    $tenantBEventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $tenantBEvent->id,
            )
            ->firstOrFail();

    /*
     * Officer sekolah A tidak boleh mengetahui apakah event
     * sekolah B benar-benar ada.
     */
    $this
        ->actingAs(
            $this->officerA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $tenantBEvent->id,
                $tenantBEventStudent->id,
            ),
            [
                'reason' =>
                    'Percobaan pembatalan lintas tenant',
            ],
        )
        ->assertNotFound()
        ->assertJsonPath(
            'message',
            'Transaksi penjemputan tidak ditemukan.',
        );

    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $tenantBEvent->id,

            'school_id' =>
                $this->schoolB->id,

            'status' =>
                PickupEvent::STATUS_CONFIRMED,

            'cancelled_at' =>
                null,

            'cancelled_by_user_id' =>
                null,
        ],
    );

    $this->assertDatabaseHas(
        'pickup_event_students',
        [
            'id' =>
                $tenantBEventStudent->id,

            'pickup_event_id' =>
                $tenantBEvent->id,

            'status' =>
                PickupEventStudent::STATUS_RELEASED,

            'cancelled_at' =>
                null,

            'cancelled_by_user_id' =>
                null,
        ],
    );
}

public function test_event_student_cannot_be_cancelled_through_different_parent_event(): void
{
    $firstEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            pickupPersonName:
                'First Parent Event',
        );

    $secondEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            pickupPersonName:
                'Second Parent Event',
        );

    $firstEventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $firstEvent->id,
            )
            ->firstOrFail();

    $secondEventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $secondEvent->id,
            )
            ->firstOrFail();

    /*
     * URL menggunakan firstEvent sebagai parent tetapi memakai
     * ID siswa milik secondEvent.
     */
    $this
        ->actingAs(
            $this->officerA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $firstEvent->id,
                $secondEventStudent->id,
            ),
            [
                'reason' =>
                    'Percobaan mengganti parent event',
            ],
        )
        ->assertNotFound()
        ->assertJsonPath(
            'message',
            'Data siswa pada transaksi tidak ditemukan.',
        );

    /*
     * Siswa milik firstEvent tidak boleh ikut berubah.
     */
    $this->assertDatabaseHas(
        'pickup_event_students',
        [
            'id' =>
                $firstEventStudent->id,

            'pickup_event_id' =>
                $firstEvent->id,

            'status' =>
                PickupEventStudent::STATUS_RELEASED,

            'cancelled_at' =>
                null,
        ],
    );

    /*
     * Siswa yang ID-nya disalahgunakan juga tidak boleh berubah.
     */
    $this->assertDatabaseHas(
        'pickup_event_students',
        [
            'id' =>
                $secondEventStudent->id,

            'pickup_event_id' =>
                $secondEvent->id,

            'status' =>
                PickupEventStudent::STATUS_RELEASED,

            'cancelled_at' =>
                null,
        ],
    );

    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $firstEvent->id,

            'status' =>
                PickupEvent::STATUS_CONFIRMED,

            'cancelled_at' =>
                null,
        ],
    );

    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $secondEvent->id,

            'status' =>
                PickupEvent::STATUS_CONFIRMED,

            'cancelled_at' =>
                null,
        ],
    );
}

public function test_partial_student_cancellation_keeps_parent_confirmed_and_updates_history_summary(): void
{
    $searchTarget =
        sprintf(
            'Partial Cancellation %s',
            Str::lower(
                Str::random(8),
            ),
        );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                $searchTarget,
        );

    $secondStudent =
        $this->createStudent(
            $this->schoolA,
        );

    $secondEventStudent =
        $this->addReleasedStudentToHistoryEvent(
            event:
                $event,

            school:
                $this->schoolA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $secondStudent,
        );

    $firstEventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->where(
                'student_id',
                $this->studentA->id,
            )
            ->firstOrFail();

    $reason =
        'Pembatalan parsial siswa pertama';

    $this
        ->actingAs(
            $this->officerA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $firstEventStudent->id,
            ),
            [
                'reason' =>
                    $reason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.id',
            (int) $event->id,
        )
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CONFIRMED,
        );

    /*
     * Parent tetap confirmed karena siswa kedua masih released.
     */
    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $event->id,

            'status' =>
                PickupEvent::STATUS_CONFIRMED,

            'cancelled_at' =>
                null,

            'cancelled_by_user_id' =>
                null,

            'cancellation_reason' =>
                null,
        ],
    );

    $this->assertDatabaseHas(
        'pickup_event_students',
        [
            'id' =>
                $firstEventStudent->id,

            'status' =>
                PickupEventStudent::STATUS_CANCELLED,

            'cancelled_by_user_id' =>
                $this->officerA->id,

            'cancellation_reason' =>
                $reason,
        ],
    );

    $this->assertDatabaseHas(
        'pickup_event_students',
        [
            'id' =>
                $secondEventStudent->id,

            'status' =>
                PickupEventStudent::STATUS_RELEASED,

            'cancelled_at' =>
                null,

            'cancelled_by_user_id' =>
                null,
        ],
    );

    /*
     * Summary history harus mengikuti kondisi terbaru:
     * satu transaksi confirmed, satu siswa released,
     * dan satu siswa cancelled.
     */
    $this
        ->get(
            '/gate/pickup-events?'
            .http_build_query([
                'search' =>
                    $searchTarget,

                'per_page' =>
                    10,
            ]),
        )
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page,
            ): Assert =>
                $page
                    ->component(
                        'gate/pickup-events/index',
                    )
                    ->has(
                        'pickupEvents.data',
                        1,
                    )
                    ->where(
                        'pickupEvents.data.0.id',
                        (int) $event->id,
                    )
                    ->where(
                        'pickupEvents.data.0.status',
                        PickupEvent::STATUS_CONFIRMED,
                    )
                    ->where(
                        'pickupEvents.data.0.student_count',
                        2,
                    )
                    ->where(
                        'pickupEvents.data.0.released_student_count',
                        1,
                    )
                    ->where(
                        'pickupEvents.data.0.cancelled_student_count',
                        1,
                    )
                    ->where(
                        'summary.total_transactions',
                        1,
                    )
                    ->where(
                        'summary.confirmed_transactions',
                        1,
                    )
                    ->where(
                        'summary.cancelled_transactions',
                        0,
                    )
                    ->where(
                        'summary.released_students',
                        1,
                    )
                    ->where(
                        'summary.cancelled_students',
                        1,
                    ),
        );
}

public function test_cancelling_last_released_student_cancels_parent_and_preserves_previous_student_audit(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Last Released Student State Machine',
        );

    $secondStudent =
        $this->createStudent(
            $this->schoolA,
        );

    $secondEventStudent =
        $this->addReleasedStudentToHistoryEvent(
            event:
                $event,

            school:
                $this->schoolA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $secondStudent,
        );

    $firstEventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->where(
                'student_id',
                $this->studentA->id,
            )
            ->firstOrFail();

    $firstReason =
        'Pembatalan siswa pertama';

    $this
        ->actingAs(
            $this->officerA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $firstEventStudent->id,
            ),
            [
                'reason' =>
                    $firstReason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CONFIRMED,
        );

    $firstEventStudent->refresh();

    $firstCancelledAt =
        $firstEventStudent
            ->cancelled_at
            ?->toIso8601String();

    $this->assertNotNull(
        $firstCancelledAt,
    );

    /*
     * Pembatalan kedua dilakukan pada waktu berbeda agar audit
     * pertama dan audit kedua dapat dibedakan dengan jelas.
     */
    CarbonImmutable::setTestNow(
        CarbonImmutable::now()
            ->addMinute(),
    );

    $secondReason =
        'Pembatalan siswa terakhir';

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $secondEventStudent->id,
            ),
            [
                'reason' =>
                    $secondReason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.id',
            (int) $event->id,
        )
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CANCELLED,
        )
        ->assertJsonPath(
            'pickup_event.cancelled_by.id',
            (int) $this->officerA->id,
        );

    $event->refresh();
    $firstEventStudent->refresh();
    $secondEventStudent->refresh();

    /*
     * Audit siswa pertama tidak boleh ditimpa oleh pembatalan kedua.
     */
    $this->assertSame(
        $firstReason,
        $firstEventStudent
            ->cancellation_reason,
    );

    $this->assertSame(
        $firstCancelledAt,
        $firstEventStudent
            ->cancelled_at
            ?->toIso8601String(),
    );

    $this->assertSame(
        (int) $this->officerA->id,
        (int) $firstEventStudent
            ->cancelled_by_user_id,
    );

    /*
     * Siswa kedua menggunakan audit pembatalan kedua.
     */
    $this->assertSame(
        PickupEventStudent::STATUS_CANCELLED,
        $secondEventStudent->status,
    );

    $this->assertSame(
        $secondReason,
        $secondEventStudent
            ->cancellation_reason,
    );

    $this->assertSame(
        (int) $this->officerA->id,
        (int) $secondEventStudent
            ->cancelled_by_user_id,
    );

    /*
     * Parent mengikuti pembatalan siswa terakhir.
     */
    $this->assertSame(
        PickupEvent::STATUS_CANCELLED,
        $event->status,
    );

    $this->assertSame(
        $secondReason,
        $event->cancellation_reason,
    );

    $this->assertSame(
        (int) $this->officerA->id,
        (int) $event
            ->cancelled_by_user_id,
    );

    $this->assertSame(
        $secondEventStudent
            ->cancelled_at
            ?->toIso8601String(),
        $event
            ->cancelled_at
            ?->toIso8601String(),
    );
}

public function test_repeated_student_cancellation_is_rejected_without_overwriting_audit(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Repeated Student Cancellation',
        );

    $secondStudent =
        $this->createStudent(
            $this->schoolA,
        );

    $secondEventStudent =
        $this->addReleasedStudentToHistoryEvent(
            event:
                $event,

            school:
                $this->schoolA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $secondStudent,
        );

    $firstEventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->where(
                'student_id',
                $this->studentA->id,
            )
            ->firstOrFail();

    $originalReason =
        'Alasan pembatalan pertama yang sah';

    $this
        ->actingAs(
            $this->officerA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $firstEventStudent->id,
            ),
            [
                'reason' =>
                    $originalReason,
            ],
        )
        ->assertOk();

    $firstEventStudent->refresh();

    $originalCancelledAt =
        $firstEventStudent
            ->cancelled_at
            ?->toIso8601String();

    $originalCancelledBy =
        (int) $firstEventStudent
            ->cancelled_by_user_id;

    CarbonImmutable::setTestNow(
        CarbonImmutable::now()
            ->addMinutes(2),
    );

    /*
     * Parent masih confirmed karena siswa kedua belum dibatalkan.
     * Karena itu request mencapai pemeriksaan status siswa.
     */
    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $firstEventStudent->id,
            ),
            [
                'reason' =>
                    'Alasan percobaan kedua yang tidak boleh tersimpan',
            ],
        )
        ->assertStatus(
            409,
        )
        ->assertJsonPath(
            'message',
            'Penyerahan siswa ini sudah dibatalkan.',
        );

    $event->refresh();
    $firstEventStudent->refresh();
    $secondEventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_CANCELLED,
        $firstEventStudent->status,
    );

    $this->assertSame(
        $originalReason,
        $firstEventStudent
            ->cancellation_reason,
    );

    $this->assertSame(
        $originalCancelledAt,
        $firstEventStudent
            ->cancelled_at
            ?->toIso8601String(),
    );

    $this->assertSame(
        $originalCancelledBy,
        (int) $firstEventStudent
            ->cancelled_by_user_id,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $secondEventStudent->status,
    );

    $this->assertNull(
        $secondEventStudent
            ->cancelled_at,
    );
}

public function test_whole_event_cancellation_after_partial_cancellation_preserves_existing_student_audit(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Whole Event After Partial Cancellation',
        );

    $secondStudent =
        $this->createStudent(
            $this->schoolA,
        );

    $secondEventStudent =
        $this->addReleasedStudentToHistoryEvent(
            event:
                $event,

            school:
                $this->schoolA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $secondStudent,
        );

    $firstEventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->where(
                'student_id',
                $this->studentA->id,
            )
            ->firstOrFail();

    $partialReason =
        'Pembatalan parsial sebelum parent dibatalkan';

    $this
        ->actingAs(
            $this->officerA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $firstEventStudent->id,
            ),
            [
                'reason' =>
                    $partialReason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CONFIRMED,
        );

    $firstEventStudent->refresh();

    $partialCancelledAt =
        $firstEventStudent
            ->cancelled_at
            ?->toIso8601String();

    $partialCancelledBy =
        (int) $firstEventStudent
            ->cancelled_by_user_id;

    CarbonImmutable::setTestNow(
        CarbonImmutable::now()
            ->addMinute(),
    );

    $wholeEventReason =
        'Pembatalan seluruh transaksi setelah koreksi parsial';

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    $wholeEventReason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CANCELLED,
        )
        ->assertJsonPath(
            'pickup_event.cancelled_by.id',
            (int) $this->officerA->id,
        );

    $event->refresh();
    $firstEventStudent->refresh();
    $secondEventStudent->refresh();

    /*
     * Siswa yang sudah cancelled harus dilewati oleh pembatalan parent.
     */
    $this->assertSame(
        $partialReason,
        $firstEventStudent
            ->cancellation_reason,
    );

    $this->assertSame(
        $partialCancelledAt,
        $firstEventStudent
            ->cancelled_at
            ?->toIso8601String(),
    );

    $this->assertSame(
        $partialCancelledBy,
        (int) $firstEventStudent
            ->cancelled_by_user_id,
    );

    /*
     * Siswa yang sebelumnya masih released mengikuti pembatalan parent.
     */
    $this->assertSame(
        PickupEventStudent::STATUS_CANCELLED,
        $secondEventStudent->status,
    );

    $this->assertSame(
        $wholeEventReason,
        $secondEventStudent
            ->cancellation_reason,
    );

    $this->assertSame(
        (int) $this->officerA->id,
        (int) $secondEventStudent
            ->cancelled_by_user_id,
    );

    /*
     * Parent memakai audit pembatalan seluruh transaksi.
     */
    $this->assertSame(
        PickupEvent::STATUS_CANCELLED,
        $event->status,
    );

    $this->assertSame(
        $wholeEventReason,
        $event->cancellation_reason,
    );

    $this->assertSame(
        (int) $this->officerA->id,
        (int) $event
            ->cancelled_by_user_id,
    );

    $this->assertSame(
        $secondEventStudent
            ->cancelled_at
            ?->toIso8601String(),
        $event
            ->cancelled_at
            ?->toIso8601String(),
    );
}

public function test_whole_event_cancellation_rejects_invalid_reason_without_mutation(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Invalid Whole Event Cancellation Reason',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $invalidPayloads = [
        'missing' =>
            [],

        'null' => [
            'reason' =>
                null,
        ],

        'whitespace_only' => [
            'reason' =>
                '      ',
        ],

        'too_short_after_trim' => [
            'reason' =>
                '  abcd  ',
        ],

        'not_string' => [
            'reason' => [
                'invalid-array-value',
            ],
        ],

        'too_long' => [
            'reason' =>
                str_repeat(
                    'A',
                    1001,
                ),
        ],
    ];

    $this->actingAs(
        $this->officerA,
    );

    foreach (
        $invalidPayloads as
        $caseName => $payload
    ) {
        $this
            ->patchJson(
                sprintf(
                    '/gate/pickup-events/%d/cancel',
                    $event->id,
                ),
                $payload,
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reason',
            ]);

        /*
         * Setiap request invalid wajib bersifat nonmutating.
         */
        $event->refresh();
        $eventStudent->refresh();

        $this->assertSame(
            PickupEvent::STATUS_CONFIRMED,
            $event->status,
            "Parent event berubah pada kasus invalid [{$caseName}].",
        );

        $this->assertNull(
            $event->cancelled_at,
            "cancelled_at parent terisi pada kasus invalid [{$caseName}].",
        );

        $this->assertNull(
            $event->cancelled_by_user_id,
            "cancelled_by parent terisi pada kasus invalid [{$caseName}].",
        );

        $this->assertNull(
            $event->cancellation_reason,
            "Alasan parent tersimpan pada kasus invalid [{$caseName}].",
        );

        $this->assertSame(
            PickupEventStudent::STATUS_RELEASED,
            $eventStudent->status,
            "Status siswa berubah pada kasus invalid [{$caseName}].",
        );

        $this->assertNull(
            $eventStudent->cancelled_at,
            "cancelled_at siswa terisi pada kasus invalid [{$caseName}].",
        );

        $this->assertNull(
            $eventStudent->cancelled_by_user_id,
            "cancelled_by siswa terisi pada kasus invalid [{$caseName}].",
        );

        $this->assertNull(
            $eventStudent->cancellation_reason,
            "Alasan siswa tersimpan pada kasus invalid [{$caseName}].",
        );
    }
}

public function test_student_cancellation_rejects_invalid_reason_without_mutation(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Invalid Student Cancellation Reason',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $invalidPayloads = [
        'missing' =>
            [],

        'null' => [
            'reason' =>
                null,
        ],

        'whitespace_only' => [
            'reason' =>
                " \n\t ",
        ],

        'too_short_after_trim' => [
            'reason' =>
                '   abcd   ',
        ],

        'not_string' => [
            'reason' => [
                'invalid',
            ],
        ],

        'too_long' => [
            'reason' =>
                str_repeat(
                    'B',
                    1001,
                ),
        ],
    ];

    $this->actingAs(
        $this->officerA,
    );

    foreach (
        $invalidPayloads as
        $caseName => $payload
    ) {
        $this
            ->patchJson(
                sprintf(
                    '/gate/pickup-events/%d/students/%d/cancel',
                    $event->id,
                    $eventStudent->id,
                ),
                $payload,
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reason',
            ]);

        $event->refresh();
        $eventStudent->refresh();

        $this->assertSame(
            PickupEvent::STATUS_CONFIRMED,
            $event->status,
            "Parent event berubah pada kasus invalid [{$caseName}].",
        );

        $this->assertNull(
            $event->cancelled_at,
            "cancelled_at parent terisi pada kasus invalid [{$caseName}].",
        );

        $this->assertNull(
            $event->cancelled_by_user_id,
            "cancelled_by parent terisi pada kasus invalid [{$caseName}].",
        );

        $this->assertNull(
            $event->cancellation_reason,
            "Alasan parent tersimpan pada kasus invalid [{$caseName}].",
        );

        $this->assertSame(
            PickupEventStudent::STATUS_RELEASED,
            $eventStudent->status,
            "Status siswa berubah pada kasus invalid [{$caseName}].",
        );

        $this->assertNull(
            $eventStudent->cancelled_at,
            "cancelled_at siswa terisi pada kasus invalid [{$caseName}].",
        );

        $this->assertNull(
            $eventStudent->cancelled_by_user_id,
            "cancelled_by siswa terisi pada kasus invalid [{$caseName}].",
        );

        $this->assertNull(
            $eventStudent->cancellation_reason,
            "Alasan siswa tersimpan pada kasus invalid [{$caseName}].",
        );
    }
}

public function test_whole_event_cancellation_trims_reason_before_storing_audit(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Normalized Whole Event Cancellation',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $rawReason =
        "   Koreksi transaksi penjemputan oleh petugas   \n";

    $normalizedReason =
        'Koreksi transaksi penjemputan oleh petugas';

    $this
        ->actingAs(
            $this->officerA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    $rawReason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CANCELLED,
        )
        ->assertJsonPath(
            'pickup_event.cancellation_reason',
            $normalizedReason,
        );

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        $normalizedReason,
        $event->cancellation_reason,
    );

    $this->assertSame(
        $normalizedReason,
        $eventStudent
            ->cancellation_reason,
    );

    $this->assertNotSame(
        $rawReason,
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEvent::STATUS_CANCELLED,
        $event->status,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_CANCELLED,
        $eventStudent->status,
    );

    $this->assertSame(
        (int) $this->officerA->id,
        (int) $event
            ->cancelled_by_user_id,
    );

    $this->assertSame(
        (int) $this->officerA->id,
        (int) $eventStudent
            ->cancelled_by_user_id,
    );
}

public function test_student_cancellation_trims_reason_and_keeps_parent_audit_empty_when_partial(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Normalized Partial Student Cancellation',
        );

    $secondStudent =
        $this->createStudent(
            $this->schoolA,
        );

    $secondEventStudent =
        $this->addReleasedStudentToHistoryEvent(
            event:
                $event,

            school:
                $this->schoolA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $secondStudent,
        );

    $firstEventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->where(
                'student_id',
                $this->studentA->id,
            )
            ->firstOrFail();

    $rawReason =
        "\t   Koreksi penyerahan siswa pertama   \n";

    $normalizedReason =
        'Koreksi penyerahan siswa pertama';

    $this
        ->actingAs(
            $this->officerA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $firstEventStudent->id,
            ),
            [
                'reason' =>
                    $rawReason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CONFIRMED,
        );

    $event->refresh();
    $firstEventStudent->refresh();
    $secondEventStudent->refresh();

    /*
     * Siswa yang dipilih memperoleh audit yang sudah dinormalisasi.
     */
    $this->assertSame(
        PickupEventStudent::STATUS_CANCELLED,
        $firstEventStudent->status,
    );

    $this->assertSame(
        $normalizedReason,
        $firstEventStudent
            ->cancellation_reason,
    );

    $this->assertNotSame(
        $rawReason,
        $firstEventStudent
            ->cancellation_reason,
    );

    $this->assertSame(
        (int) $this->officerA->id,
        (int) $firstEventStudent
            ->cancelled_by_user_id,
    );

    $this->assertNotNull(
        $firstEventStudent
            ->cancelled_at,
    );

    /*
     * Siswa kedua tidak boleh berubah.
     */
    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $secondEventStudent->status,
    );

    $this->assertNull(
        $secondEventStudent
            ->cancelled_at,
    );

    $this->assertNull(
        $secondEventStudent
            ->cancelled_by_user_id,
    );

    $this->assertNull(
        $secondEventStudent
            ->cancellation_reason,
    );

    /*
     * Parent belum dibatalkan karena masih ada satu siswa released.
     */
    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );
}

public function test_gate_officer_can_cancel_own_event_exactly_at_window_boundary(): void
{
    config()->set(
        'biometrics.security.gate_cancellation_window_seconds',
        300,
    );

    $fixedNow =
        CarbonImmutable::parse(
            '2026-07-22 10:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $fixedNow,
    );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow->subSeconds(
                    300,
                ),

            pickupPersonName:
                'Exact Event Cancellation Boundary',
        );

    /*
     * Tepat 300 detik berarti belum lebih lama dari window.
     * History dan detail harus konsisten menampilkan can_cancel=true.
     */
    $this
        ->actingAs(
            $this->officerA,
        )
        ->get(
            '/gate/pickup-events?'
            .http_build_query([
                'search' =>
                    'Exact Event Cancellation Boundary',

                'per_page' =>
                    10,
            ]),
        )
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page,
            ): Assert =>
                $page
                    ->component(
                        'gate/pickup-events/index',
                    )
                    ->has(
                        'pickupEvents.data',
                        1,
                    )
                    ->where(
                        'pickupEvents.data.0.id',
                        (int) $event->id,
                    )
                    ->where(
                        'pickupEvents.data.0.can_cancel',
                        true,
                    ),
        );

    $this
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.id',
            (int) $event->id,
        )
        ->assertJsonPath(
            'pickup_event.can_cancel',
            true,
        );

    $reason =
        'Pembatalan tepat pada batas lima menit';

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    $reason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CANCELLED,
        )
        ->assertJsonPath(
            'pickup_event.cancelled_by.id',
            (int) $this->officerA->id,
        )
        ->assertJsonPath(
            'pickup_event.cancellation_reason',
            $reason,
        );

    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $event->id,

            'status' =>
                PickupEvent::STATUS_CANCELLED,

            'cancelled_by_user_id' =>
                $this->officerA->id,

            'cancellation_reason' =>
                $reason,
        ],
    );

    $this->assertDatabaseHas(
        'pickup_event_students',
        [
            'pickup_event_id' =>
                $event->id,

            'status' =>
                PickupEventStudent::STATUS_CANCELLED,

            'cancelled_by_user_id' =>
                $this->officerA->id,

            'cancellation_reason' =>
                $reason,
        ],
    );
}

public function test_gate_officer_cannot_cancel_own_event_one_second_after_window_boundary(): void
{
    config()->set(
        'biometrics.security.gate_cancellation_window_seconds',
        300,
    );

    $fixedNow =
        CarbonImmutable::parse(
            '2026-07-22 10:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $fixedNow,
    );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow->subSeconds(
                    301,
                ),

            pickupPersonName:
                'Expired Event Cancellation Boundary',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    /*
     * History dan detail harus sama-sama menyatakan tidak dapat dibatalkan.
     */
    $this
        ->actingAs(
            $this->officerA,
        )
        ->get(
            '/gate/pickup-events?'
            .http_build_query([
                'search' =>
                    'Expired Event Cancellation Boundary',

                'per_page' =>
                    10,
            ]),
        )
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page,
            ): Assert =>
                $page
                    ->component(
                        'gate/pickup-events/index',
                    )
                    ->has(
                        'pickupEvents.data',
                        1,
                    )
                    ->where(
                        'pickupEvents.data.0.id',
                        (int) $event->id,
                    )
                    ->where(
                        'pickupEvents.data.0.can_cancel',
                        false,
                    ),
        );

    $this
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.can_cancel',
            false,
        );

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Percobaan pembatalan lewat satu detik',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Batas pembatalan oleh petugas gerbang adalah 5 menit. Hubungi administrator sekolah.',
        );

    /*
     * Penolakan boundary tidak boleh mengubah audit.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_gate_officer_can_cancel_student_exactly_at_window_boundary(): void
{
    config()->set(
        'biometrics.security.gate_cancellation_window_seconds',
        300,
    );

    $fixedNow =
        CarbonImmutable::parse(
            '2026-07-22 11:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $fixedNow,
    );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow->subSeconds(
                    300,
                ),

            pickupPersonName:
                'Exact Student Cancellation Boundary',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $this
        ->actingAs(
            $this->officerA,
        )
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.can_cancel',
            true,
        )
        ->assertJsonPath(
            'pickup_event.students.0.can_cancel',
            true,
        );

    $reason =
        'Pembatalan siswa tepat pada batas waktu';

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    $reason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CANCELLED,
        )
        ->assertJsonPath(
            'pickup_event.cancelled_by.id',
            (int) $this->officerA->id,
        );

    /*
     * Fixture memiliki satu siswa sehingga pembatalan siswa terakhir
     * juga membatalkan parent event.
     */
    $this->assertDatabaseHas(
        'pickup_event_students',
        [
            'id' =>
                $eventStudent->id,

            'status' =>
                PickupEventStudent::STATUS_CANCELLED,

            'cancelled_by_user_id' =>
                $this->officerA->id,

            'cancellation_reason' =>
                $reason,
        ],
    );

    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $event->id,

            'status' =>
                PickupEvent::STATUS_CANCELLED,

            'cancelled_by_user_id' =>
                $this->officerA->id,

            'cancellation_reason' =>
                $reason,
        ],
    );
}

public function test_gate_officer_cannot_cancel_student_one_second_after_window_boundary(): void
{
    config()->set(
        'biometrics.security.gate_cancellation_window_seconds',
        300,
    );

    $fixedNow =
        CarbonImmutable::parse(
            '2026-07-22 11:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $fixedNow,
    );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow->subSeconds(
                    301,
                ),

            pickupPersonName:
                'Expired Student Cancellation Boundary',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $this
        ->actingAs(
            $this->officerA,
        )
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.can_cancel',
            false,
        )
        ->assertJsonPath(
            'pickup_event.students.0.can_cancel',
            false,
        );

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'Percobaan pembatalan siswa lewat batas',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Batas pembatalan oleh petugas gerbang adalah 5 menit. Hubungi administrator sekolah.',
        );

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_cancellation_rechecks_window_after_detail_was_loaded(): void
{
    config()->set(
        'biometrics.security.gate_cancellation_window_seconds',
        300,
    );

    $initialNow =
        CarbonImmutable::parse(
            '2026-07-22 12:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $initialNow,
    );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $initialNow->subSeconds(
                    299,
                ),

            pickupPersonName:
                'Cancellation Window Recheck',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    /*
     * Saat detail dibuka, transaksi masih berada dalam window.
     */
    $this
        ->actingAs(
            $this->officerA,
        )
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.can_cancel',
            true,
        )
        ->assertJsonPath(
            'pickup_event.students.0.can_cancel',
            true,
        );

    /*
     * Dua detik kemudian usia transaksi menjadi 301 detik.
     * Backend tidak boleh memercayai nilai can_cancel dari response lama.
     */
    CarbonImmutable::setTestNow(
        $initialNow->addSeconds(
            2,
        ),
    );

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Percobaan pembatalan berdasarkan detail lama',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Batas pembatalan oleh petugas gerbang adalah 5 menit. Hubungi administrator sekolah.',
        );

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_cancellation_window_configuration_is_clamped_to_minimum_sixty_seconds(): void
{
    config()->set(
        'biometrics.security.gate_cancellation_window_seconds',
        1,
    );

    $fixedNow =
        CarbonImmutable::parse(
            '2026-07-22 13:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $fixedNow,
    );

    $boundaryEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow->subSeconds(
                    60,
                ),

            pickupPersonName:
                'Minimum Window Boundary',
        );

    $expiredEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow->subSeconds(
                    61,
                ),

            pickupPersonName:
                'Minimum Window Expired',
        );

    $this->actingAs(
        $this->officerA,
    );

    /*
     * Tepat 60 detik tetap diperbolehkan karena nilai konfigurasi
     * 1 detik harus dinaikkan menjadi minimum 60 detik.
     */
    $this
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $boundaryEvent->id,
            ),
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.can_cancel',
            true,
        );

    $boundaryReason =
        'Pembatalan pada minimum window';

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $boundaryEvent->id,
            ),
            [
                'reason' =>
                    $boundaryReason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CANCELLED,
        );

    /*
     * Usia 61 detik harus ditolak.
     */
    $this
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $expiredEvent->id,
            ),
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.can_cancel',
            false,
        );

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $expiredEvent->id,
            ),
            [
                'reason' =>
                    'Percobaan setelah minimum window',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Batas pembatalan oleh petugas gerbang adalah 1 menit. Hubungi administrator sekolah.',
        );

    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $boundaryEvent->id,

            'status' =>
                PickupEvent::STATUS_CANCELLED,

            'cancellation_reason' =>
                $boundaryReason,
        ],
    );

    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $expiredEvent->id,

            'status' =>
                PickupEvent::STATUS_CONFIRMED,

            'cancelled_at' =>
                null,

            'cancelled_by_user_id' =>
                null,

            'cancellation_reason' =>
                null,
        ],
    );
}

public function test_cancellation_window_configuration_is_clamped_to_maximum_one_day(): void
{
    config()->set(
        'biometrics.security.gate_cancellation_window_seconds',
        999999,
    );

    $fixedNow =
        CarbonImmutable::parse(
            '2026-07-22 14:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $fixedNow,
    );

    $boundaryEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow->subSeconds(
                    86400,
                ),

            pickupPersonName:
                'Maximum Window Boundary',
        );

    $expiredEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow->subSeconds(
                    86401,
                ),

            pickupPersonName:
                'Maximum Window Expired',
        );

    $this->actingAs(
        $this->officerA,
    );

    /*
     * Tepat satu hari masih boleh dibatalkan.
     */
    $this
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $boundaryEvent->id,
            ),
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.can_cancel',
            true,
        );

    $boundaryReason =
        'Pembatalan tepat pada maksimum satu hari';

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $boundaryEvent->id,
            ),
            [
                'reason' =>
                    $boundaryReason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CANCELLED,
        );

    /*
     * Lebih lama satu detik harus ditolak walaupun konfigurasi
     * berisi angka yang jauh lebih besar.
     */
    $this
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $expiredEvent->id,
            ),
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.can_cancel',
            false,
        );

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $expiredEvent->id,
            ),
            [
                'reason' =>
                    'Percobaan setelah batas maksimum satu hari',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Batas pembatalan oleh petugas gerbang adalah 1440 menit. Hubungi administrator sekolah.',
        );

    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $boundaryEvent->id,

            'status' =>
                PickupEvent::STATUS_CANCELLED,

            'cancellation_reason' =>
                $boundaryReason,
        ],
    );

    $this->assertDatabaseHas(
        'pickup_events',
        [
            'id' =>
                $expiredEvent->id,

            'status' =>
                PickupEvent::STATUS_CONFIRMED,

            'cancelled_at' =>
                null,

            'cancelled_by_user_id' =>
                null,

            'cancellation_reason' =>
                null,
        ],
    );
}

public function test_repeated_whole_event_cancellation_is_rejected_without_overwriting_audit(): void
{
    $fixedNow =
        CarbonImmutable::parse(
            '2026-07-22 15:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $fixedNow,
    );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow,

            pickupPersonName:
                'Repeated Whole Event Cancellation',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $originalReason =
        'Pembatalan pertama yang sah';

    $this
        ->actingAs(
            $this->officerA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    $originalReason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CANCELLED,
        )
        ->assertJsonPath(
            'pickup_event.cancellation_reason',
            $originalReason,
        );

    $event->refresh();
    $eventStudent->refresh();

    $originalEventCancelledAt =
        $event
            ->cancelled_at
            ?->toIso8601String();

    $originalEventCancelledBy =
        (int) $event
            ->cancelled_by_user_id;

    $originalStudentCancelledAt =
        $eventStudent
            ->cancelled_at
            ?->toIso8601String();

    $originalStudentCancelledBy =
        (int) $eventStudent
            ->cancelled_by_user_id;

    $this->assertNotNull(
        $originalEventCancelledAt,
    );

    $this->assertNotNull(
        $originalStudentCancelledAt,
    );

    /*
     * Waktu dimajukan agar terlihat apabila audit pertama
     * secara tidak sengaja ditimpa oleh request kedua.
     */
    CarbonImmutable::setTestNow(
        $fixedNow->addMinutes(
            2,
        ),
    );

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Alasan kedua yang tidak boleh tersimpan',
            ],
        )
        ->assertStatus(
            409,
        )
        ->assertJsonPath(
            'message',
            'Transaksi penjemputan sudah dibatalkan atau tidak dapat dibatalkan.',
        );

    $event->refresh();
    $eventStudent->refresh();

    /*
     * Parent event harus tetap memakai audit pembatalan pertama.
     */
    $this->assertSame(
        PickupEvent::STATUS_CANCELLED,
        $event->status,
    );

    $this->assertSame(
        $originalReason,
        $event->cancellation_reason,
    );

    $this->assertSame(
        $originalEventCancelledAt,
        $event
            ->cancelled_at
            ?->toIso8601String(),
    );

    $this->assertSame(
        $originalEventCancelledBy,
        (int) $event
            ->cancelled_by_user_id,
    );

    /*
     * Snapshot siswa juga tidak boleh berubah.
     */
    $this->assertSame(
        PickupEventStudent::STATUS_CANCELLED,
        $eventStudent->status,
    );

    $this->assertSame(
        $originalReason,
        $eventStudent
            ->cancellation_reason,
    );

    $this->assertSame(
        $originalStudentCancelledAt,
        $eventStudent
            ->cancelled_at
            ?->toIso8601String(),
    );

    $this->assertSame(
        $originalStudentCancelledBy,
        (int) $eventStudent
            ->cancelled_by_user_id,
    );
}

public function test_cancelled_parent_blocks_student_recancellation_even_for_school_admin(): void
{
    $fixedNow =
        CarbonImmutable::parse(
            '2026-07-22 16:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $fixedNow,
    );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow,

            pickupPersonName:
                'Cancelled Parent Student Protection',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $originalReason =
        'Pembatalan transaksi oleh petugas gerbang';

    $this
        ->actingAs(
            $this->officerA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    $originalReason,
            ],
        )
        ->assertOk();

    $event->refresh();
    $eventStudent->refresh();

    $originalEventCancelledAt =
        $event
            ->cancelled_at
            ?->toIso8601String();

    $originalStudentCancelledAt =
        $eventStudent
            ->cancelled_at
            ?->toIso8601String();

    $originalEventCancelledBy =
        (int) $event
            ->cancelled_by_user_id;

    $originalStudentCancelledBy =
        (int) $eventStudent
            ->cancelled_by_user_id;

    CarbonImmutable::setTestNow(
        $fixedNow->addHour(),
    );

    /*
     * Administrator mempunyai hak koreksi transaksi lama,
     * tetapi tidak boleh mengubah kembali transaksi terminal.
     */
    $this
        ->actingAs(
            $this->adminA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'Percobaan administrator menimpa audit siswa',
            ],
        )
        ->assertStatus(
            409,
        )
        ->assertJsonPath(
            'message',
            'Transaksi penjemputan sudah dibatalkan atau tidak dapat diubah.',
        );

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CANCELLED,
        $event->status,
    );

    $this->assertSame(
        $originalReason,
        $event->cancellation_reason,
    );

    $this->assertSame(
        $originalEventCancelledAt,
        $event
            ->cancelled_at
            ?->toIso8601String(),
    );

    $this->assertSame(
        $originalEventCancelledBy,
        (int) $event
            ->cancelled_by_user_id,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_CANCELLED,
        $eventStudent->status,
    );

    $this->assertSame(
        $originalReason,
        $eventStudent
            ->cancellation_reason,
    );

    $this->assertSame(
        $originalStudentCancelledAt,
        $eventStudent
            ->cancelled_at
            ?->toIso8601String(),
    );

    $this->assertSame(
        $originalStudentCancelledBy,
        (int) $eventStudent
            ->cancelled_by_user_id,
    );

    /*
     * Pembatalan awal dilakukan oleh officerA.
     * Audit tidak boleh berubah menjadi adminA.
     */
    $this->assertSame(
        (int) $this->officerA->id,
        (int) $event
            ->cancelled_by_user_id,
    );

    $this->assertSame(
        (int) $this->officerA->id,
        (int) $eventStudent
            ->cancelled_by_user_id,
    );
}

public function test_cancelled_event_detail_and_history_have_consistent_terminal_state(): void
{
    $searchTarget =
        sprintf(
            'Terminal State %s',
            Str::lower(
                Str::random(8),
            ),
        );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                $searchTarget,
        );

    $secondStudent =
        $this->createStudent(
            $this->schoolA,
        );

    $this->addReleasedStudentToHistoryEvent(
        event:
            $event,

        school:
            $this->schoolA,

        pickupPerson:
            $this->pickupPersonA,

        student:
            $secondStudent,
    );

    $reason =
        'Pembatalan terminal seluruh transaksi';

    $this
        ->actingAs(
            $this->officerA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    $reason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CANCELLED,
        )
        ->assertJsonPath(
            'pickup_event.can_cancel',
            false,
        );

    /*
     * Detail terminal:
     * parent dan seluruh siswa tidak dapat dibatalkan lagi.
     */
    $this
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.id',
            (int) $event->id,
        )
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CANCELLED,
        )
        ->assertJsonPath(
            'pickup_event.can_cancel',
            false,
        )
        ->assertJsonPath(
            'pickup_event.cancellation_reason',
            $reason,
        )
        ->assertJson(
            function (
                \Illuminate\Testing\Fluent\AssertableJson $json,
            ): void {
                $json
                    ->has(
                        'pickup_event.students',
                        2,
                    )
                    ->where(
                        'pickup_event.students',
                        function (
                            mixed $students,
                        ): bool {
                            $students =
                                $this->normalizeInertiaValue(
                                    $students,
                                );

                            $this->assertIsArray(
                                $students,
                            );

                            $this->assertCount(
                                2,
                                $students,
                            );

                            foreach (
                                $students as
                                $student
                            ) {
                                $this->assertIsArray(
                                    $student,
                                );

                                $this->assertSame(
                                    PickupEventStudent::STATUS_CANCELLED,
                                    $student[
                                        'status'
                                    ],
                                );

                                $this->assertFalse(
                                    (bool) $student[
                                        'can_cancel'
                                    ],
                                );
                            }

                            return true;
                        },
                    )
                    ->etc();
            },
        );

    /*
     * History dan summary harus mengikuti kondisi terminal terbaru.
     */
    $this
        ->get(
            '/gate/pickup-events?'
            .http_build_query([
                'search' =>
                    $searchTarget,

                'per_page' =>
                    10,
            ]),
        )
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page,
            ): Assert =>
                $page
                    ->component(
                        'gate/pickup-events/index',
                    )
                    ->has(
                        'pickupEvents.data',
                        1,
                    )
                    ->where(
                        'pickupEvents.data.0.id',
                        (int) $event->id,
                    )
                    ->where(
                        'pickupEvents.data.0.status',
                        PickupEvent::STATUS_CANCELLED,
                    )
                    ->where(
                        'pickupEvents.data.0.can_cancel',
                        false,
                    )
                    ->where(
                        'pickupEvents.data.0.student_count',
                        2,
                    )
                    ->where(
                        'pickupEvents.data.0.released_student_count',
                        0,
                    )
                    ->where(
                        'pickupEvents.data.0.cancelled_student_count',
                        2,
                    )
                    ->where(
                        'summary.total_transactions',
                        1,
                    )
                    ->where(
                        'summary.confirmed_transactions',
                        0,
                    )
                    ->where(
                        'summary.cancelled_transactions',
                        1,
                    )
                    ->where(
                        'summary.released_students',
                        0,
                    )
                    ->where(
                        'summary.cancelled_students',
                        2,
                    ),
        );
}

public function test_partial_event_detail_exposes_student_specific_cancellation_permissions_for_confirming_officer(): void
{
    $fixedNow =
        CarbonImmutable::parse(
            '2026-07-22 17:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $fixedNow,
    );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow,

            pickupPersonName:
                'Partial Detail Confirming Officer',
        );

    $secondStudent =
        $this->createStudent(
            $this->schoolA,
        );

    $secondEventStudent =
        $this->addReleasedStudentToHistoryEvent(
            event:
                $event,

            school:
                $this->schoolA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $secondStudent,
        );

    $firstEventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->where(
                'student_id',
                $this->studentA->id,
            )
            ->firstOrFail();

    $partialReason =
        'Pembatalan parsial untuk kontrak detail';

    $this
        ->actingAs(
            $this->officerA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $firstEventStudent->id,
            ),
            [
                'reason' =>
                    $partialReason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CONFIRMED,
        );

    $response =
        $this
            ->getJson(
                sprintf(
                    '/gate/pickup-events/%d',
                    $event->id,
                ),
            )
            ->assertOk()
            ->assertJsonPath(
                'pickup_event.id',
                (int) $event->id,
            )
            ->assertJsonPath(
                'pickup_event.status',
                PickupEvent::STATUS_CONFIRMED,
            )
            ->assertJsonPath(
                'pickup_event.can_cancel',
                true,
            )
            ->assertJsonCount(
                2,
                'pickup_event.students',
            );

    $students =
        $response->json(
            'pickup_event.students',
        );

    $this->assertIsArray(
        $students,
    );

    $studentsById =
        collect(
            $students,
        )->keyBy(
            static fn (
                array $student,
            ): int =>
                (int) $student['id'],
        );

    $cancelledStudentPayload =
        $studentsById->get(
            (int) $firstEventStudent->id,
        );

    $releasedStudentPayload =
        $studentsById->get(
            (int) $secondEventStudent->id,
        );

    $this->assertIsArray(
        $cancelledStudentPayload,
    );

    $this->assertIsArray(
        $releasedStudentPayload,
    );

    /*
     * Siswa pertama sudah cancelled sehingga tidak dapat
     * dibatalkan kembali.
     */
    $this->assertSame(
        PickupEventStudent::STATUS_CANCELLED,
        $cancelledStudentPayload[
            'status'
        ],
    );

    $this->assertFalse(
        (bool) $cancelledStudentPayload[
            'can_cancel'
        ],
    );

    $this->assertSame(
        $partialReason,
        $cancelledStudentPayload[
            'cancellation_reason'
        ],
    );

    $this->assertSame(
        (int) $this->officerA->id,
        (int) $cancelledStudentPayload[
            'cancelled_by'
        ]['id'],
    );

    /*
     * Siswa kedua masih released dan event masih berada
     * dalam cancellation window.
     */
    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $releasedStudentPayload[
            'status'
        ],
    );

    $this->assertTrue(
        (bool) $releasedStudentPayload[
            'can_cancel'
        ],
    );

    $this->assertNull(
        $releasedStudentPayload[
            'cancelled_at'
        ],
    );

    $this->assertNull(
        $releasedStudentPayload[
            'cancelled_by'
        ],
    );

    $this->assertNull(
        $releasedStudentPayload[
            'cancellation_reason'
        ],
    );
}

public function test_other_gate_officer_can_view_partial_event_but_cannot_cancel_parent_or_students(): void
{
    $fixedNow =
        CarbonImmutable::parse(
            '2026-07-22 18:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $fixedNow,
    );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow,

            pickupPersonName:
                'Partial Detail Other Officer',
        );

    $secondStudent =
        $this->createStudent(
            $this->schoolA,
        );

    $secondEventStudent =
        $this->addReleasedStudentToHistoryEvent(
            event:
                $event,

            school:
                $this->schoolA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $secondStudent,
        );

    $firstEventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->where(
                'student_id',
                $this->studentA->id,
            )
            ->firstOrFail();

    $partialReason =
        'Pembatalan parsial oleh petugas pembuat';

    $this
        ->actingAs(
            $this->officerA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $firstEventStudent->id,
            ),
            [
                'reason' =>
                    $partialReason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CONFIRMED,
        );

    /*
     * Officer B berasal dari sekolah yang sama sehingga boleh
     * melihat detail, tetapi bukan pembuat transaksi.
     */
    $response =
        $this
            ->actingAs(
                $this->officerB,
            )
            ->getJson(
                sprintf(
                    '/gate/pickup-events/%d',
                    $event->id,
                ),
            )
            ->assertOk()
            ->assertJsonPath(
                'pickup_event.id',
                (int) $event->id,
            )
            ->assertJsonPath(
                'pickup_event.status',
                PickupEvent::STATUS_CONFIRMED,
            )
            ->assertJsonPath(
                'pickup_event.can_cancel',
                false,
            )
            ->assertJsonCount(
                2,
                'pickup_event.students',
            );

    $students =
        $response->json(
            'pickup_event.students',
        );

    $this->assertIsArray(
        $students,
    );

    $studentsById =
        collect(
            $students,
        )->keyBy(
            static fn (
                array $student,
            ): int =>
                (int) $student['id'],
        );

    $cancelledStudentPayload =
        $studentsById->get(
            (int) $firstEventStudent->id,
        );

    $releasedStudentPayload =
        $studentsById->get(
            (int) $secondEventStudent->id,
        );

    $this->assertIsArray(
        $cancelledStudentPayload,
    );

    $this->assertIsArray(
        $releasedStudentPayload,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_CANCELLED,
        $cancelledStudentPayload[
            'status'
        ],
    );

    $this->assertFalse(
        (bool) $cancelledStudentPayload[
            'can_cancel'
        ],
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $releasedStudentPayload[
            'status'
        ],
    );

    /*
     * Walaupun siswa kedua masih released, Officer B tidak
     * memperoleh hak karena event dikonfirmasi Officer A.
     */
    $this->assertFalse(
        (bool) $releasedStudentPayload[
            'can_cancel'
        ],
    );

    /*
     * Percobaan mutasi juga harus ditolak.
     */
    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $secondEventStudent->id,
            ),
            [
                'reason' =>
                    'Percobaan pembatalan oleh petugas lain',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Petugas gerbang hanya dapat membatalkan transaksi yang dikonfirmasi sendiri.',
        );

    $event->refresh();
    $secondEventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $secondEventStudent->status,
    );

    $this->assertNull(
        $secondEventStudent
            ->cancelled_at,
    );

    $this->assertNull(
        $secondEventStudent
            ->cancelled_by_user_id,
    );

    $this->assertNull(
        $secondEventStudent
            ->cancellation_reason,
    );
}

public function test_school_admin_partial_old_event_detail_only_allows_released_students_to_be_cancelled(): void
{
    $fixedNow =
        CarbonImmutable::parse(
            '2026-07-22 19:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $fixedNow,
    );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow->subDays(
                    7,
                ),

            pickupPersonName:
                'Partial Old Event Administrator',
        );

    $secondStudent =
        $this->createStudent(
            $this->schoolA,
        );

    $secondEventStudent =
        $this->addReleasedStudentToHistoryEvent(
            event:
                $event,

            school:
                $this->schoolA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $secondStudent,
        );

    $firstEventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->where(
                'student_id',
                $this->studentA->id,
            )
            ->firstOrFail();

    $partialReason =
        'Koreksi administratif siswa pertama';

    /*
     * Administrator dapat melakukan koreksi pada event lama.
     */
    $this
        ->actingAs(
            $this->adminA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $firstEventStudent->id,
            ),
            [
                'reason' =>
                    $partialReason,
            ],
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CONFIRMED,
        );

    $response =
        $this
            ->getJson(
                sprintf(
                    '/gate/pickup-events/%d',
                    $event->id,
                ),
            )
            ->assertOk()
            ->assertJsonPath(
                'pickup_event.id',
                (int) $event->id,
            )
            ->assertJsonPath(
                'pickup_event.status',
                PickupEvent::STATUS_CONFIRMED,
            )
            ->assertJsonPath(
                'pickup_event.can_cancel',
                true,
            )
            ->assertJsonCount(
                2,
                'pickup_event.students',
            );

    $students =
        $response->json(
            'pickup_event.students',
        );

    $this->assertIsArray(
        $students,
    );

    $studentsById =
        collect(
            $students,
        )->keyBy(
            static fn (
                array $student,
            ): int =>
                (int) $student['id'],
        );

    $cancelledStudentPayload =
        $studentsById->get(
            (int) $firstEventStudent->id,
        );

    $releasedStudentPayload =
        $studentsById->get(
            (int) $secondEventStudent->id,
        );

    $this->assertIsArray(
        $cancelledStudentPayload,
    );

    $this->assertIsArray(
        $releasedStudentPayload,
    );

    /*
     * Hak administrator pada parent tidak membuat siswa
     * terminal dapat dibatalkan ulang.
     */
    $this->assertSame(
        PickupEventStudent::STATUS_CANCELLED,
        $cancelledStudentPayload[
            'status'
        ],
    );

    $this->assertFalse(
        (bool) $cancelledStudentPayload[
            'can_cancel'
        ],
    );

    $this->assertSame(
        $partialReason,
        $cancelledStudentPayload[
            'cancellation_reason'
        ],
    );

    /*
     * Siswa yang masih released tetap dapat dikoreksi walaupun
     * event sudah berusia tujuh hari.
     */
    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $releasedStudentPayload[
            'status'
        ],
    );

    $this->assertTrue(
        (bool) $releasedStudentPayload[
            'can_cancel'
        ],
    );

    $this->assertNull(
        $releasedStudentPayload[
            'cancelled_at'
        ],
    );

    $this->assertNull(
        $releasedStudentPayload[
            'cancelled_by'
        ],
    );

    $this->assertNull(
        $releasedStudentPayload[
            'cancellation_reason'
        ],
    );
}

public function test_cancellation_rechecks_account_active_status_after_detail_was_loaded(): void
{
    $fixedNow =
        CarbonImmutable::parse(
            '2026-07-22 20:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $fixedNow,
    );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow,

            pickupPersonName:
                'Inactive Account TOCTOU',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    /*
     * Pada saat detail dibuka, akun masih aktif dan mempunyai
     * hak pembatalan.
     */
    $this
        ->actingAs(
            $this->officerA,
        )
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.can_cancel',
            true,
        )
        ->assertJsonPath(
            'pickup_event.students.0.can_cancel',
            true,
        );

    /*
     * Akun dinonaktifkan sebelum request mutasi dikirim.
     */
    $this->officerA->forceFill([
        'is_active' =>
            false,
    ])->save();

    $this->officerA->refresh();

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Percobaan memakai authorization detail lama',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun Anda sedang tidak aktif.',
        );

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_student_cancellation_rechecks_user_role_after_detail_was_loaded(): void
{
    $fixedNow =
        CarbonImmutable::parse(
            '2026-07-22 21:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $fixedNow,
    );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow,

            pickupPersonName:
                'Role Change TOCTOU',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    /*
     * Detail awal diberikan ketika pengguna masih berperan
     * sebagai gate officer.
     */
    $this
        ->actingAs(
            $this->officerA,
        )
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.can_cancel',
            true,
        )
        ->assertJsonPath(
            'pickup_event.students.0.can_cancel',
            true,
        );

    /*
     * Role dicabut sebelum endpoint pembatalan siswa dipanggil.
     */
    $this->officerA->forceFill([
        'role' =>
            'teacher',
    ])->save();

    $this->officerA->refresh();

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'Percobaan memakai hak role sebelumnya',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun tidak memiliki izin mengelola transaksi gerbang.',
        );

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_cancellation_rechecks_tenant_after_admin_was_moved_to_another_school(): void
{
    $fixedNow =
        CarbonImmutable::parse(
            '2026-07-22 22:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $fixedNow,
    );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow->subDays(
                    7,
                ),

            pickupPersonName:
                'Tenant Move TOCTOU',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    /*
     * Administrator sekolah A awalnya mempunyai hak koreksi
     * terhadap event lama.
     */
    $this
        ->actingAs(
            $this->adminA,
        )
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.id',
            (int) $event->id,
        )
        ->assertJsonPath(
            'pickup_event.can_cancel',
            true,
        );

    /*
     * Administrator kemudian dipindahkan ke tenant B.
     * Hak administrator tidak boleh melampaui tenant terbaru.
     */
    $this->adminA->forceFill([
        'school_id' =>
            $this->schoolB->id,
    ])->save();

    $this->adminA->refresh();

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Percobaan koreksi setelah berpindah sekolah',
            ],
        )
        ->assertNotFound()
        ->assertJsonPath(
            'message',
            'Transaksi penjemputan tidak ditemukan.',
        );

    $event->refresh();
    $eventStudent->refresh();

    /*
     * Event sekolah A tidak boleh berubah.
     */
    $this->assertSame(
        (int) $this->schoolA->id,
        (int) $event->school_id,
    );

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );

    /*
     * Setelah berpindah tenant, detail event lama juga harus
     * disamarkan sebagai tidak ditemukan.
     */
    $this
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertNotFound()
        ->assertJsonPath(
            'message',
            'Transaksi penjemputan tidak ditemukan.',
        );
}

public function test_school_admin_without_school_cannot_access_history_detail_or_cancel(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Administrator Without School',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    /*
     * Role administrator tetap valid, tetapi school binding
     * dilepas sebelum request dilakukan.
     */
    $this->adminA->forceFill([
        'school_id' =>
            null,
    ])->save();

    $this->adminA->refresh();

    $this
        ->actingAs(
            $this->adminA,
        )
        ->get(
            '/gate/pickup-events',
        )
        ->assertForbidden();

    $this
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun belum terhubung dengan sekolah.',
        );

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Percobaan administrator tanpa sekolah',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun belum terhubung dengan sekolah.',
        );

    /*
     * Seluruh data transaksi harus tetap utuh.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_student_cancellation_rechecks_school_binding_after_detail_was_loaded(): void
{
    $fixedNow =
        CarbonImmutable::parse(
            '2026-07-22 23:00:00',
            'UTC',
        );

    CarbonImmutable::setTestNow(
        $fixedNow,
    );

    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                $fixedNow,

            pickupPersonName:
                'School Binding TOCTOU',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    /*
     * Saat detail dibuka, Officer A masih terhubung dengan
     * sekolah A dan mempunyai hak pembatalan.
     */
    $this
        ->actingAs(
            $this->officerA,
        )
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.id',
            (int) $event->id,
        )
        ->assertJsonPath(
            'pickup_event.can_cancel',
            true,
        )
        ->assertJsonPath(
            'pickup_event.students.0.can_cancel',
            true,
        );

    /*
     * School binding dilepas setelah response detail diterima,
     * tetapi sebelum endpoint mutasi dipanggil.
     */
    $this->officerA->forceFill([
        'school_id' =>
            null,
    ])->save();

    $this->officerA->refresh();

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'Percobaan memakai school binding lama',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun belum terhubung dengan sekolah.',
        );

    /*
     * Detail event juga tidak boleh lagi dapat diakses.
     */
    $this
        ->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun belum terhubung dengan sekolah.',
        );

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_inactive_account_error_takes_precedence_over_missing_school_on_cancellation_requests(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Inactive And Missing School Precedence',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    /*
     * Dua kondisi invalid terjadi bersamaan.
     * Status inactive harus diperiksa lebih dahulu.
     */
    $this->officerA->forceFill([
        'is_active' =>
            false,

        'school_id' =>
            null,
    ])->save();

    $this->officerA->refresh();

    $this->actingAs(
        $this->officerA,
    );

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Percobaan event oleh akun inactive tanpa sekolah',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun Anda sedang tidak aktif.',
        );

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'Percobaan siswa oleh akun inactive tanpa sekolah',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun Anda sedang tidak aktif.',
        );

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_missing_school_error_takes_precedence_over_invalid_role_on_cancellation_requests(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Missing School And Invalid Role Precedence',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    /*
     * Akun tetap aktif, tetapi role dan school binding
     * sama-sama tidak valid.
     */
    $this->officerA->forceFill([
        'is_active' =>
            true,

        'school_id' =>
            null,

        'role' =>
            'teacher',
    ])->save();

    $this->officerA->refresh();

    $this->actingAs(
        $this->officerA,
    );

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Percobaan event oleh guru tanpa sekolah',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun belum terhubung dengan sekolah.',
        );

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'Percobaan siswa oleh guru tanpa sekolah',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun belum terhubung dengan sekolah.',
        );

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_invalid_role_error_is_returned_when_account_and_school_binding_are_valid(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Invalid Role Authorization Fallback',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    /*
     * Hanya role yang tidak valid.
     */
    $this->officerA->forceFill([
        'is_active' =>
            true,

        'school_id' =>
            $this->schoolA->id,

        'role' =>
            'teacher',
    ])->save();

    $this->officerA->refresh();

    $this->actingAs(
        $this->officerA,
    );

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Percobaan event menggunakan role guru',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun tidak memiliki izin mengelola transaksi gerbang.',
        );

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'Percobaan siswa menggunakan role guru',
            ],
        )
        ->assertForbidden()
        ->assertJsonPath(
            'message',
            'Akun tidak memiliki izin mengelola transaksi gerbang.',
        );

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_guest_cannot_cancel_event_or_student_and_receives_unauthorized_response(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Guest Cancellation Protection',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    /*
     * Pembatalan seluruh event tanpa autentikasi.
     */
    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Percobaan pembatalan event tanpa login',
            ],
        )
        ->assertStatus(
            401,
        )
        ->assertJsonPath(
            'message',
            'Silakan masuk untuk melanjutkan.',
        );

    /*
     * Pembatalan siswa tanpa autentikasi.
     */
    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'Percobaan pembatalan siswa tanpa login',
            ],
        )
        ->assertStatus(
            401,
        )
        ->assertJsonPath(
            'message',
            'Silakan masuk untuk melanjutkan.',
        );

    /*
     * Kedua request tidak boleh memutasi data.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_guest_authentication_error_takes_precedence_over_reason_validation(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Guest Validation Precedence',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    /*
     * Payload sengaja tidak memiliki reason.
     * Guest tetap harus menerima 401, bukan 422.
     */
    $eventResponse =
        $this->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [],
        );

    $eventResponse
        ->assertStatus(
            401,
        )
        ->assertJsonPath(
            'message',
            'Silakan masuk untuk melanjutkan.',
        );

    $this->assertNull(
        $eventResponse->json(
            'errors',
        ),
        'Guest tidak boleh menerima detail validation error event sebelum autentikasi.',
    );

    $studentResponse =
        $this->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    '   ',
            ],
        );

    $studentResponse
        ->assertStatus(
            401,
        )
        ->assertJsonPath(
            'message',
            'Silakan masuk untuk melanjutkan.',
        );

    $this->assertNull(
        $studentResponse->json(
            'errors',
        ),
        'Guest tidak boleh menerima detail validation error siswa sebelum autentikasi.',
    );

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_guest_json_requests_use_localized_authentication_contract_and_conceal_event_existence(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Guest Resource Concealment',
        );

    $missingEventId =
        (int) $event->id
        + 999999;

    /*
     * History JSON harus memakai kontrak autentikasi
     * yang telah dilokalkan.
     */
    $historyResponse =
        $this->getJson(
            '/gate/pickup-events',
        );

    $historyResponse
        ->assertStatus(
            401,
        )
        ->assertExactJson([
            'message' =>
                'Silakan masuk untuk melanjutkan.',
        ]);

    /*
     * Detail event yang benar-benar ada tetap ditolak
     * sebelum resource dibaca.
     */
    $existingDetailResponse =
        $this->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        );

    $existingDetailResponse
        ->assertStatus(
            401,
        )
        ->assertExactJson([
            'message' =>
                'Silakan masuk untuk melanjutkan.',
        ]);

    /*
     * ID yang tidak ada harus menghasilkan kontrak identik.
     * Guest tidak boleh mengetahui apakah ID event valid.
     */
    $missingDetailResponse =
        $this->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $missingEventId,
            ),
        );

    $missingDetailResponse
        ->assertStatus(
            401,
        )
        ->assertExactJson([
            'message' =>
                'Silakan masuk untuk melanjutkan.',
        ]);

    $this->assertSame(
        $existingDetailResponse->getStatusCode(),
        $missingDetailResponse->getStatusCode(),
    );

    $this->assertSame(
        $existingDetailResponse->json(),
        $missingDetailResponse->json(),
    );
}

public function test_guest_browser_requests_still_redirect_to_login_after_json_authentication_handler_is_registered(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Guest Browser Authentication Redirect',
        );

    $missingEventId =
        (int) $event->id
        + 999999;

    /*
     * Request browser tidak mengirim Accept application/json.
     * Handler AuthenticationException harus mengembalikan null
     * sehingga mekanisme redirect Laravel tetap berjalan.
     */
    $this
        ->get(
            '/gate/pickup-events',
        )
        ->assertRedirect(
            '/login',
        );

    $this
        ->get(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertRedirect(
            '/login',
        );

    /*
     * Resource yang tidak ada juga tidak boleh menghasilkan 404
     * sebelum pengguna melakukan autentikasi.
     */
    $this
        ->get(
            sprintf(
                '/gate/pickup-events/%d',
                $missingEventId,
            ),
        )
        ->assertRedirect(
            '/login',
        );
}

public function test_guest_event_cancellation_conceals_whether_event_exists(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Guest Event Mutation Concealment',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $missingEventId =
        (int) $event->id
        + 999999;

    $payload = [
        'reason' =>
            'Percobaan pembatalan tanpa autentikasi',
    ];

    /*
     * Event yang benar-benar ada.
     */
    $existingEventResponse =
        $this->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            $payload,
        );

    $existingEventResponse
        ->assertStatus(
            401,
        )
        ->assertExactJson([
            'message' =>
                'Silakan masuk untuk melanjutkan.',
        ]);

    /*
     * Event yang tidak ada harus memberikan response identik.
     */
    $missingEventResponse =
        $this->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $missingEventId,
            ),
            $payload,
        );

    $missingEventResponse
        ->assertStatus(
            401,
        )
        ->assertExactJson([
            'message' =>
                'Silakan masuk untuk melanjutkan.',
        ]);

    $this->assertSame(
        $existingEventResponse->getStatusCode(),
        $missingEventResponse->getStatusCode(),
    );

    $this->assertSame(
        $existingEventResponse->json(),
        $missingEventResponse->json(),
    );

    /*
     * Event yang ada tidak boleh berubah.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_guest_student_cancellation_conceals_parent_and_student_existence(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Guest Student Mutation Concealment',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $missingEventId =
        (int) $event->id
        + 999999;

    $missingEventStudentId =
        (int) $eventStudent->id
        + 999999;

    $payload = [
        'reason' =>
            'Percobaan pembatalan siswa tanpa autentikasi',
    ];

    $requestTargets = [
        [
            'event_id' =>
                (int) $event->id,

            'event_student_id' =>
                (int) $eventStudent->id,
        ],
        [
            'event_id' =>
                (int) $event->id,

            'event_student_id' =>
                $missingEventStudentId,
        ],
        [
            'event_id' =>
                $missingEventId,

            'event_student_id' =>
                (int) $eventStudent->id,
        ],
        [
            'event_id' =>
                $missingEventId,

            'event_student_id' =>
                $missingEventStudentId,
        ],
    ];

    $referenceStatus =
        null;

    $referencePayload =
        null;

    foreach (
        $requestTargets as
        $index =>
        $requestTarget
    ) {
        $response =
            $this->patchJson(
                sprintf(
                    '/gate/pickup-events/%d/students/%d/cancel',
                    $requestTarget[
                        'event_id'
                    ],
                    $requestTarget[
                        'event_student_id'
                    ],
                ),
                $payload,
            );

        $response
            ->assertStatus(
                401,
            )
            ->assertExactJson([
                'message' =>
                    'Silakan masuk untuk melanjutkan.',
            ]);

        if ($index === 0) {
            $referenceStatus =
                $response->getStatusCode();

            $referencePayload =
                $response->json();

            continue;
        }

        /*
         * Semua kombinasi harus memberikan kontrak identik.
         */
        $this->assertSame(
            $referenceStatus,
            $response->getStatusCode(),
        );

        $this->assertSame(
            $referencePayload,
            $response->json(),
        );
    }

    /*
     * Resource yang benar-benar ada tetap tidak berubah.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_authenticated_user_receives_not_found_for_malformed_event_detail_identifiers(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Malformed Detail Identifier',
        );

    $this->actingAs(
        $this->adminA,
    );

    $malformedIdentifiers = [
        'not-a-number',
        sprintf(
            '%d.0',
            $event->id,
        ),
        sprintf(
            '-%d',
            $event->id,
        ),
    ];

    foreach (
        $malformedIdentifiers as
        $malformedIdentifier
    ) {
        $this
            ->getJson(
                sprintf(
                    '/gate/pickup-events/%s',
                    $malformedIdentifier,
                ),
            )
            ->assertNotFound();
    }

    /*
     * Request detail tidak boleh mengubah transaksi.
     */
    $event->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );
}

public function test_malformed_event_identifier_cannot_reach_whole_event_cancellation(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Malformed Event Cancellation Identifier',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $this->actingAs(
        $this->adminA,
    );

    $malformedIdentifiers = [
        sprintf(
            '%d.0',
            $event->id,
        ),
        'not-a-number',
        sprintf(
            '-%d',
            $event->id,
        ),
    ];

    foreach (
        $malformedIdentifiers as
        $malformedIdentifier
    ) {
        $this
            ->patchJson(
                sprintf(
                    '/gate/pickup-events/%s/cancel',
                    $malformedIdentifier,
                ),
                [
                    'reason' =>
                        'Identifier event tidak valid',
                ],
            )
            ->assertNotFound();
    }

    /*
     * Nilai desimal seperti "123.0" tidak boleh dikonversi
     * menjadi ID event 123 dan menjalankan pembatalan.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_malformed_parent_or_student_identifier_cannot_reach_student_cancellation(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Malformed Student Cancellation Identifier',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $this->actingAs(
        $this->adminA,
    );

    $malformedTargets = [
        [
            'event_id' =>
                sprintf(
                    '%d.0',
                    $event->id,
                ),

            'event_student_id' =>
                (string) $eventStudent->id,
        ],
        [
            'event_id' =>
                (string) $event->id,

            'event_student_id' =>
                sprintf(
                    '%d.0',
                    $eventStudent->id,
                ),
        ],
        [
            'event_id' =>
                'not-a-number',

            'event_student_id' =>
                (string) $eventStudent->id,
        ],
        [
            'event_id' =>
                (string) $event->id,

            'event_student_id' =>
                'not-a-number',
        ],
        [
            'event_id' =>
                sprintf(
                    '-%d',
                    $event->id,
                ),

            'event_student_id' =>
                sprintf(
                    '-%d',
                    $eventStudent->id,
                ),
        ],
    ];

    foreach (
        $malformedTargets as
        $malformedTarget
    ) {
        $this
            ->patchJson(
                sprintf(
                    '/gate/pickup-events/%s/students/%s/cancel',
                    $malformedTarget[
                        'event_id'
                    ],
                    $malformedTarget[
                        'event_student_id'
                    ],
                ),
                [
                    'reason' =>
                        'Identifier siswa tidak valid',
                ],
            )
            ->assertNotFound();
    }

    /*
     * Tidak satu pun parameter malformed boleh dimutasi
     * menjadi identifier integer yang valid.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_oversized_numeric_event_identifier_returns_not_found_for_detail(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Oversized Numeric Detail Identifier',
        );

    $oversizedIdentifier =
        str_repeat(
            '9',
            64,
        );

    $this
        ->actingAs(
            $this->adminA,
        )
        ->getJson(
            sprintf(
                '/gate/pickup-events/%s',
                $oversizedIdentifier,
            ),
        )
        ->assertNotFound();

    /*
     * Request detail tidak boleh mengubah transaksi.
     */
    $event->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );
}

public function test_oversized_numeric_event_identifier_cannot_reach_whole_event_cancellation(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Oversized Numeric Event Cancellation',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $oversizedIdentifier =
        str_repeat(
            '9',
            64,
        );

    $this
        ->actingAs(
            $this->adminA,
        )
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%s/cancel',
                $oversizedIdentifier,
            ),
            [
                'reason' =>
                    'Identifier numerik melampaui kapasitas integer',
            ],
        )
        ->assertNotFound();

    /*
     * Identifier oversized tidak boleh dikonversi atau
     * menyebabkan mutasi pada event yang valid.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_oversized_numeric_parent_or_student_identifier_cannot_reach_student_cancellation(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Oversized Numeric Student Cancellation',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $oversizedIdentifier =
        str_repeat(
            '9',
            64,
        );

    $requestTargets = [
        [
            'event_id' =>
                $oversizedIdentifier,

            'event_student_id' =>
                (string) $eventStudent->id,
        ],
        [
            'event_id' =>
                (string) $event->id,

            'event_student_id' =>
                $oversizedIdentifier,
        ],
        [
            'event_id' =>
                $oversizedIdentifier,

            'event_student_id' =>
                $oversizedIdentifier,
        ],
    ];

    $this->actingAs(
        $this->adminA,
    );

    foreach (
        $requestTargets as
        $requestTarget
    ) {
        $this
            ->patchJson(
                sprintf(
                    '/gate/pickup-events/%s/students/%s/cancel',
                    $requestTarget[
                        'event_id'
                    ],
                    $requestTarget[
                        'event_student_id'
                    ],
                ),
                [
                    'reason' =>
                        'Identifier parent atau siswa terlalu besar',
                ],
            )
            ->assertNotFound();
    }

    /*
     * Seluruh request oversized harus nonmutating.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_maximum_64_bit_integer_identifier_is_accepted_without_type_error(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Maximum Integer Route Boundary',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $maximumIntegerIdentifier =
        '9223372036854775807';

    $this->actingAs(
        $this->adminA,
    );

    /*
     * Nilai maksimum masih boleh melewati route.
     * Karena record tidak ada, hasil akhirnya 404 dari controller,
     * bukan 500 TypeError.
     */
    $this
        ->getJson(
            sprintf(
                '/gate/pickup-events/%s',
                $maximumIntegerIdentifier,
            ),
        )
        ->assertNotFound();

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%s/cancel',
                $maximumIntegerIdentifier,
            ),
            [
                'reason' =>
                    'Pengujian batas maksimum integer event',
            ],
        )
        ->assertNotFound();

    /*
     * Child ID maksimum juga harus dapat dikirim ke controller
     * sebagai integer tanpa TypeError.
     */
    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%s/cancel',
                $event->id,
                $maximumIntegerIdentifier,
            ),
            [
                'reason' =>
                    'Pengujian batas maksimum integer siswa',
            ],
        )
        ->assertNotFound();

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_first_integer_above_64_bit_maximum_is_rejected_before_controller(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'First Integer Overflow Boundary',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $firstOverflowIdentifier =
        '9223372036854775808';

    $this->actingAs(
        $this->adminA,
    );

    $this
        ->getJson(
            sprintf(
                '/gate/pickup-events/%s',
                $firstOverflowIdentifier,
            ),
        )
        ->assertNotFound();

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%s/cancel',
                $firstOverflowIdentifier,
            ),
            [
                'reason' =>
                    'Identifier event berada satu angka di atas batas',
            ],
        )
        ->assertNotFound();

    /*
     * Overflow pada parent.
     */
    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%s/students/%d/cancel',
                $firstOverflowIdentifier,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'Identifier parent melampaui batas integer',
            ],
        )
        ->assertNotFound();

    /*
     * Overflow pada child.
     */
    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%s/cancel',
                $event->id,
                $firstOverflowIdentifier,
            ),
            [
                'reason' =>
                    'Identifier siswa melampaui batas integer',
            ],
        )
        ->assertNotFound();

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_zero_and_leading_zero_identifiers_are_rejected_without_mutation(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Canonical Numeric Identifier',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $this->actingAs(
        $this->adminA,
    );

    $invalidEventIdentifiers = [
        '0',
        '00',
        '01',
        sprintf(
            '0%d',
            $event->id,
        ),
    ];

    foreach (
        $invalidEventIdentifiers as
        $invalidEventIdentifier
    ) {
        $this
            ->getJson(
                sprintf(
                    '/gate/pickup-events/%s',
                    $invalidEventIdentifier,
                ),
            )
            ->assertNotFound();

        $this
            ->patchJson(
                sprintf(
                    '/gate/pickup-events/%s/cancel',
                    $invalidEventIdentifier,
                ),
                [
                    'reason' =>
                        'Identifier event tidak canonical',
                ],
            )
            ->assertNotFound();
    }

    $invalidStudentIdentifiers = [
        '0',
        '00',
        '01',
        sprintf(
            '0%d',
            $eventStudent->id,
        ),
    ];

    foreach (
        $invalidStudentIdentifiers as
        $invalidStudentIdentifier
    ) {
        $this
            ->patchJson(
                sprintf(
                    '/gate/pickup-events/%d/students/%s/cancel',
                    $event->id,
                    $invalidStudentIdentifier,
                ),
                [
                    'reason' =>
                        'Identifier siswa tidak canonical',
                ],
            )
            ->assertNotFound();
    }

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_numeric_like_encoded_and_unicode_event_identifiers_are_rejected_for_detail(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Noncanonical Detail Identifier',
        );

    $this->actingAs(
        $this->adminA,
    );

    $invalidIdentifiers = [
        '1e3',
        '1E3',
        '0x10',
        '0X10',
        '1_000',
        '1,000',
        '+123',
        rawurlencode(
            '+123',
        ),
        rawurlencode(
            ' 123',
        ),
        rawurlencode(
            '123 ',
        ),
        rawurlencode(
            '１２３',
        ),
        rawurlencode(
            '١٢٣',
        ),
    ];

    foreach (
        $invalidIdentifiers as
        $invalidIdentifier
    ) {
        $this
            ->getJson(
                sprintf(
                    '/gate/pickup-events/%s',
                    $invalidIdentifier,
                ),
            )
            ->assertNotFound();
    }

    /*
     * Request detail tidak boleh mengubah data.
     */
    $event->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );
}

public function test_numeric_like_encoded_and_unicode_identifiers_cannot_reach_whole_event_cancellation(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Noncanonical Event Cancellation Identifier',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $this->actingAs(
        $this->adminA,
    );

    $invalidIdentifiers = [
        sprintf(
            '%de0',
            $event->id,
        ),
        sprintf(
            '0x%d',
            $event->id,
        ),
        sprintf(
            '%d_0',
            $event->id,
        ),
        sprintf(
            '%d,0',
            $event->id,
        ),
        sprintf(
            '+%d',
            $event->id,
        ),
        rawurlencode(
            sprintf(
                '+%d',
                $event->id,
            ),
        ),
        rawurlencode(
            sprintf(
                ' %d',
                $event->id,
            ),
        ),
        rawurlencode(
            sprintf(
                '%d ',
                $event->id,
            ),
        ),
        rawurlencode(
            '１２３',
        ),
        rawurlencode(
            '١٢٣',
        ),
    ];

    foreach (
        $invalidIdentifiers as
        $invalidIdentifier
    ) {
        $this
            ->patchJson(
                sprintf(
                    '/gate/pickup-events/%s/cancel',
                    $invalidIdentifier,
                ),
                [
                    'reason' =>
                        'Identifier event noncanonical harus ditolak',
                ],
            )
            ->assertNotFound();
    }

    /*
     * Seluruh request harus nonmutating.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_numeric_like_encoded_and_unicode_parent_or_student_identifiers_cannot_reach_student_cancellation(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Noncanonical Student Cancellation Identifier',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $this->actingAs(
        $this->adminA,
    );

    $invalidParentIdentifiers = [
        sprintf(
            '%de0',
            $event->id,
        ),
        sprintf(
            '0x%d',
            $event->id,
        ),
        sprintf(
            '%d_0',
            $event->id,
        ),
        sprintf(
            '+%d',
            $event->id,
        ),
        rawurlencode(
            sprintf(
                ' %d',
                $event->id,
            ),
        ),
        rawurlencode(
            '１２３',
        ),
        rawurlencode(
            '١٢٣',
        ),
    ];

    foreach (
        $invalidParentIdentifiers as
        $invalidParentIdentifier
    ) {
        $this
            ->patchJson(
                sprintf(
                    '/gate/pickup-events/%s/students/%d/cancel',
                    $invalidParentIdentifier,
                    $eventStudent->id,
                ),
                [
                    'reason' =>
                        'Identifier parent noncanonical harus ditolak',
                ],
            )
            ->assertNotFound();
    }

    $invalidStudentIdentifiers = [
        sprintf(
            '%de0',
            $eventStudent->id,
        ),
        sprintf(
            '0x%d',
            $eventStudent->id,
        ),
        sprintf(
            '%d_0',
            $eventStudent->id,
        ),
        sprintf(
            '+%d',
            $eventStudent->id,
        ),
        rawurlencode(
            sprintf(
                '%d ',
                $eventStudent->id,
            ),
        ),
        rawurlencode(
            '１２３',
        ),
        rawurlencode(
            '١٢٣',
        ),
    ];

    foreach (
        $invalidStudentIdentifiers as
        $invalidStudentIdentifier
    ) {
        $this
            ->patchJson(
                sprintf(
                    '/gate/pickup-events/%d/students/%s/cancel',
                    $event->id,
                    $invalidStudentIdentifier,
                ),
                [
                    'reason' =>
                        'Identifier siswa noncanonical harus ditolak',
                ],
            )
            ->assertNotFound();
    }

    /*
     * Tidak satu pun bentuk numeric-like boleh mencapai mutasi.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_guest_json_authentication_runs_only_after_canonical_route_identifiers_match(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Guest Route Matching Precedence',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $missingCanonicalEventId =
        (int) $event->id
        + 999999;

    /*
     * ID canonical cocok dengan route.
     * Middleware autentikasi harus dijalankan sebelum controller
     * menentukan apakah resource ada.
     */
    $canonicalDetailTargets = [
        (string) $event->id,
        (string) $missingCanonicalEventId,
    ];

    foreach (
        $canonicalDetailTargets as
        $canonicalDetailTarget
    ) {
        $this
            ->getJson(
                sprintf(
                    '/gate/pickup-events/%s',
                    $canonicalDetailTarget,
                ),
            )
            ->assertStatus(
                401,
            )
            ->assertExactJson([
                'message' =>
                    'Silakan masuk untuk melanjutkan.',
            ]);
    }

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Guest menggunakan identifier canonical',
            ],
        )
        ->assertStatus(
            401,
        )
        ->assertExactJson([
            'message' =>
                'Silakan masuk untuk melanjutkan.',
        ]);

    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'Guest menggunakan identifier siswa canonical',
            ],
        )
        ->assertStatus(
            401,
        )
        ->assertExactJson([
            'message' =>
                'Silakan masuk untuk melanjutkan.',
        ]);

    /*
     * ID noncanonical tidak boleh cocok dengan route.
     * Hasilnya 404 sebelum middleware autentikasi berjalan.
     */
    $malformedEventIdentifiers = [
        'not-a-number',
        sprintf(
            '%d.0',
            $event->id,
        ),
        sprintf(
            '0%d',
            $event->id,
        ),
        str_repeat(
            '9',
            64,
        ),
        '9223372036854775808',
    ];

    foreach (
        $malformedEventIdentifiers as
        $malformedEventIdentifier
    ) {
        $this
            ->getJson(
                sprintf(
                    '/gate/pickup-events/%s',
                    $malformedEventIdentifier,
                ),
            )
            ->assertNotFound();

        $this
            ->patchJson(
                sprintf(
                    '/gate/pickup-events/%s/cancel',
                    $malformedEventIdentifier,
                ),
                [
                    'reason' =>
                        'Route malformed tidak boleh mencapai autentikasi',
                ],
            )
            ->assertNotFound();
    }

    $malformedStudentIdentifiers = [
        'not-a-number',
        sprintf(
            '%d.0',
            $eventStudent->id,
        ),
        sprintf(
            '0%d',
            $eventStudent->id,
        ),
        str_repeat(
            '9',
            64,
        ),
        '9223372036854775808',
    ];

    foreach (
        $malformedStudentIdentifiers as
        $malformedStudentIdentifier
    ) {
        $this
            ->patchJson(
                sprintf(
                    '/gate/pickup-events/%d/students/%s/cancel',
                    $event->id,
                    $malformedStudentIdentifier,
                ),
                [
                    'reason' =>
                        'Child route malformed tidak boleh diproses',
                ],
            )
            ->assertNotFound();
    }

    /*
     * Semua request guest harus nonmutating.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_guest_browser_redirects_for_canonical_identifiers_but_receives_not_found_for_malformed_routes(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Guest Browser Route Matching Precedence',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    /*
     * Identifier canonical cocok dengan route,
     * kemudian middleware auth melakukan redirect.
     */
    $this
        ->get(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertRedirect(
            '/login',
        );

    $this
        ->patch(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Guest browser menggunakan route canonical',
            ],
        )
        ->assertRedirect(
            '/login',
        );

    $this
        ->patch(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'Guest browser menggunakan child canonical',
            ],
        )
        ->assertRedirect(
            '/login',
        );

    /*
     * Route malformed tidak pernah mencapai middleware auth.
     */
    $this
        ->get(
            sprintf(
                '/gate/pickup-events/%d.0',
                $event->id,
            ),
        )
        ->assertNotFound();

    $this
        ->patch(
            sprintf(
                '/gate/pickup-events/%s/cancel',
                str_repeat(
                    '9',
                    64,
                ),
            ),
            [
                'reason' =>
                    'Guest browser menggunakan parent overflow',
            ],
        )
        ->assertNotFound();

    $this
        ->patch(
            sprintf(
                '/gate/pickup-events/%d/students/%s/cancel',
                $event->id,
                '9223372036854775808',
            ),
            [
                'reason' =>
                    'Guest browser menggunakan child overflow',
            ],
        )
        ->assertNotFound();

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_wrong_http_methods_are_rejected_without_mutating_pickup_event(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'HTTP Method Mutation Safety',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $this->actingAs(
        $this->adminA,
    );

    /*
     * Pembatalan event hanya menerima PATCH.
     */
    $this
        ->postJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'POST tidak boleh membatalkan event',
            ],
        )
        ->assertStatus(
            405,
        );

    $this
        ->putJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'PUT tidak boleh membatalkan event',
            ],
        )
        ->assertStatus(
            405,
        );

    $this
        ->deleteJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'DELETE tidak boleh membatalkan event',
            ],
        )
        ->assertStatus(
            405,
        );

    /*
     * Pembatalan siswa hanya menerima PATCH.
     */
    $this
        ->postJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'POST tidak boleh membatalkan siswa',
            ],
        )
        ->assertStatus(
            405,
        );

    $this
        ->putJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'PUT tidak boleh membatalkan siswa',
            ],
        )
        ->assertStatus(
            405,
        );

    $this
        ->deleteJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [
                'reason' =>
                    'DELETE tidak boleh membatalkan siswa',
            ],
        )
        ->assertStatus(
            405,
        );

    /*
     * Detail transaksi hanya menerima GET atau HEAD.
     */
    $this
        ->patchJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
            [
                'reason' =>
                    'PATCH tidak boleh diproses sebagai detail',
            ],
        )
        ->assertStatus(
            405,
        );

    $this
        ->putJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
            [
                'reason' =>
                    'PUT tidak boleh diproses sebagai detail',
            ],
        )
        ->assertStatus(
            405,
        );

    $this
        ->deleteJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        )
        ->assertStatus(
            405,
        );

    /*
     * Seluruh request dengan method salah harus nonmutating.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_wrong_http_methods_on_malformed_identifiers_return_not_found_instead_of_method_not_allowed(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Malformed Method Precedence',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $this->actingAs(
        $this->adminA,
    );

    /*
     * Parent malformed tidak cocok dengan route.
     * Karena route tidak cocok, respons harus 404 dan bukan 405.
     */
    $this
        ->postJson(
            '/gate/pickup-events/not-a-number/cancel',
            [
                'reason' =>
                    'Parent malformed menggunakan POST',
            ],
        )
        ->assertNotFound();

    $this
        ->deleteJson(
            sprintf(
                '/gate/pickup-events/%s/cancel',
                str_repeat(
                    '9',
                    64,
                ),
            ),
            [
                'reason' =>
                    'Parent overflow menggunakan DELETE',
            ],
        )
        ->assertNotFound();

    $this
        ->putJson(
            '/gate/pickup-events/9223372036854775808/cancel',
            [
                'reason' =>
                    'Parent di atas batas menggunakan PUT',
            ],
        )
        ->assertNotFound();

    /*
     * Child malformed juga tidak cocok dengan route.
     */
    $this
        ->postJson(
            sprintf(
                '/gate/pickup-events/%d/students/not-a-number/cancel',
                $event->id,
            ),
            [
                'reason' =>
                    'Child malformed menggunakan POST',
            ],
        )
        ->assertNotFound();

    $this
        ->putJson(
            sprintf(
                '/gate/pickup-events/%d/students/%s/cancel',
                $event->id,
                '9223372036854775808',
            ),
            [
                'reason' =>
                    'Child overflow menggunakan PUT',
            ],
        )
        ->assertNotFound();

    $this
        ->deleteJson(
            sprintf(
                '/gate/pickup-events/%d/students/%s/cancel',
                $event->id,
                sprintf(
                    '0%d',
                    $eventStudent->id,
                ),
            ),
            [
                'reason' =>
                    'Child leading zero menggunakan DELETE',
            ],
        )
        ->assertNotFound();

    /*
     * Detail malformed tetap 404 walaupun method mutasi digunakan.
     */
    $this
        ->patchJson(
            '/gate/pickup-events/0',
            [
                'reason' =>
                    'Identifier nol menggunakan PATCH',
            ],
        )
        ->assertNotFound();

    $this
        ->deleteJson(
            sprintf(
                '/gate/pickup-events/%d.0',
                $event->id,
            ),
        )
        ->assertNotFound();

    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_head_requests_follow_detail_authorization_and_never_mutate_pickup_event(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'HEAD Detail Contract',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $otherTenantEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolB,

            officer:
                $this->officerTenantB,

            pickupPerson:
                $this->pickupPersonB,

            student:
                $this->studentB,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'HEAD Other Tenant Concealment',
        );

    $missingEventId =
        (int) $event->id
        + 999999;

    $this->actingAs(
        $this->adminA,
    );

    /*
     * HEAD pada resource tenant sendiri harus berhasil
     * seperti GET, tetapi tanpa response body.
     */
    $existingResponse =
        $this->call(
            'HEAD',
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        );

    $existingResponse
        ->assertOk();

    $this->assertSame(
        '',
        $existingResponse->getContent(),
    );

    /*
     * Resource yang tidak ada tetap menghasilkan 404.
     */
    $missingResponse =
        $this->call(
            'HEAD',
            sprintf(
                '/gate/pickup-events/%d',
                $missingEventId,
            ),
        );

    $missingResponse
        ->assertNotFound();

    $this->assertSame(
        '',
        $missingResponse->getContent(),
    );

    /*
     * Resource tenant lain juga disamarkan sebagai 404.
     */
    $otherTenantResponse =
        $this->call(
            'HEAD',
            sprintf(
                '/gate/pickup-events/%d',
                $otherTenantEvent->id,
            ),
        );

    $otherTenantResponse
        ->assertNotFound();

    $this->assertSame(
        '',
        $otherTenantResponse->getContent(),
    );

    /*
     * HEAD tidak boleh mengubah parent maupun child.
     */
    $event->refresh();
    $eventStudent->refresh();
    $otherTenantEvent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $otherTenantEvent->status,
    );

    $this->assertNull(
        $otherTenantEvent->cancelled_at,
    );

    $this->assertNull(
        $otherTenantEvent->cancelled_by_user_id,
    );

    $this->assertNull(
        $otherTenantEvent->cancellation_reason,
    );
}

public function test_method_not_allowed_responses_expose_only_expected_allow_headers_without_mutation(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Method Allow Header Contract',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $this->actingAs(
        $this->adminA,
    );

    /*
     * Urutan pada header Allow dapat berbeda.
     * Normalisasi dilakukan sebelum assertion.
     */
    $normalizedAllowMethods =
        static function (
            mixed $response,
        ): array {
            return collect(
                explode(
                    ',',
                    (string) $response
                        ->headers
                        ->get(
                            'Allow',
                        ),
                ),
            )
                ->map(
                    static fn (
                        string $method,
                    ): string =>
                        trim(
                            $method,
                        ),
                )
                ->filter(
                    static fn (
                        string $method,
                    ): bool =>
                        $method !== '',
                )
                ->sort()
                ->values()
                ->all();
        };

    /*
     * URI detail hanya menerima GET dan HEAD.
     */
    $detailResponse =
        $this->postJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
            [
                'reason' =>
                    'POST tidak diizinkan pada detail',
            ],
        );

    $detailResponse
        ->assertStatus(
            405,
        )
        ->assertHeader(
            'Allow',
        );

    $this->assertSame(
        [
            'GET',
            'HEAD',
        ],
        $normalizedAllowMethods(
            $detailResponse,
        ),
    );

    /*
     * URI pembatalan seluruh event hanya menerima PATCH.
     */
    $eventCancellationResponse =
        $this->getJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
        );

    $eventCancellationResponse
        ->assertStatus(
            405,
        )
        ->assertHeader(
            'Allow',
        );

    $this->assertSame(
        [
            'PATCH',
        ],
        $normalizedAllowMethods(
            $eventCancellationResponse,
        ),
    );

    /*
     * URI pembatalan siswa juga hanya menerima PATCH.
     */
    $studentCancellationResponse =
        $this->getJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
        );

    $studentCancellationResponse
        ->assertStatus(
            405,
        )
        ->assertHeader(
            'Allow',
        );

    $this->assertSame(
        [
            'PATCH',
        ],
        $normalizedAllowMethods(
            $studentCancellationResponse,
        ),
    );

    /*
     * Pemeriksaan method tidak boleh menghasilkan mutasi.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_guest_json_wrong_methods_are_rejected_before_authentication_and_conceal_resource_existence(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Guest JSON Method Precedence',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $missingEventId =
        (int) $event->id
        + 999999;

    $missingEventStudentId =
        (int) $eventStudent->id
        + 999999;

    $normalizedAllowMethods =
        static function (
            mixed $response,
        ): array {
            return collect(
                explode(
                    ',',
                    (string) $response
                        ->headers
                        ->get(
                            'Allow',
                        ),
                ),
            )
                ->map(
                    static fn (
                        string $method,
                    ): string =>
                        trim(
                            $method,
                        ),
                )
                ->filter(
                    static fn (
                        string $method,
                    ): bool =>
                        $method !== '',
                )
                ->sort()
                ->values()
                ->all();
        };

    /*
     * URI detail menerima GET dan HEAD.
     * POST harus menghasilkan 405 sebelum auth dijalankan.
     *
     * Event existing dan missing harus memiliki kontrak sama
     * sehingga keberadaan resource tidak dapat dibedakan.
     */
    $detailResponses = [
        $this->postJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
            [
                'reason' =>
                    'Guest POST pada detail existing',
            ],
        ),

        $this->postJson(
            sprintf(
                '/gate/pickup-events/%d',
                $missingEventId,
            ),
            [
                'reason' =>
                    'Guest POST pada detail missing',
            ],
        ),
    ];

    foreach (
        $detailResponses as
        $detailResponse
    ) {
        $detailResponse
            ->assertStatus(
                405,
            )
            ->assertHeader(
                'Allow',
            );

        $this->assertSame(
            [
                'GET',
                'HEAD',
            ],
            $normalizedAllowMethods(
                $detailResponse,
            ),
        );
    }

    /*
     * URI pembatalan event hanya menerima PATCH.
     * Existing dan missing event tetap menghasilkan 405.
     */
    $eventCancellationResponses = [
        $this->getJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
        ),

        $this->getJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $missingEventId,
            ),
        ),
    ];

    foreach (
        $eventCancellationResponses as
        $eventCancellationResponse
    ) {
        $eventCancellationResponse
            ->assertStatus(
                405,
            )
            ->assertHeader(
                'Allow',
            );

        $this->assertSame(
            [
                'PATCH',
            ],
            $normalizedAllowMethods(
                $eventCancellationResponse,
            ),
        );
    }

    /*
     * URI pembatalan siswa juga hanya menerima PATCH.
     * Keberadaan parent atau child tidak boleh memengaruhi 405.
     */
    $studentCancellationTargets = [
        [
            'event_id' =>
                (int) $event->id,

            'event_student_id' =>
                (int) $eventStudent->id,
        ],
        [
            'event_id' =>
                (int) $event->id,

            'event_student_id' =>
                $missingEventStudentId,
        ],
        [
            'event_id' =>
                $missingEventId,

            'event_student_id' =>
                (int) $eventStudent->id,
        ],
        [
            'event_id' =>
                $missingEventId,

            'event_student_id' =>
                $missingEventStudentId,
        ],
    ];

    foreach (
        $studentCancellationTargets as
        $studentCancellationTarget
    ) {
        $studentCancellationResponse =
            $this->getJson(
                sprintf(
                    '/gate/pickup-events/%d/students/%d/cancel',
                    $studentCancellationTarget[
                        'event_id'
                    ],
                    $studentCancellationTarget[
                        'event_student_id'
                    ],
                ),
            );

        $studentCancellationResponse
            ->assertStatus(
                405,
            )
            ->assertHeader(
                'Allow',
            );

        $this->assertSame(
            [
                'PATCH',
            ],
            $normalizedAllowMethods(
                $studentCancellationResponse,
            ),
        );
    }

    /*
     * Seluruh request harus nonmutating.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_guest_browser_wrong_methods_return_method_not_allowed_instead_of_login_redirect(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Guest Browser Method Precedence',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    /*
     * POST pada URI detail tidak boleh diarahkan ke login.
     * Router harus lebih dahulu menghasilkan 405.
     */
    $detailResponse =
        $this->post(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
            [
                'reason' =>
                    'Guest browser POST pada detail',
            ],
        );

    $detailResponse
        ->assertStatus(
            405,
        )
        ->assertHeader(
            'Allow',
        );

    $this->assertFalse(
        $detailResponse
            ->baseResponse
            ->isRedirection(),
    );

    /*
     * GET pada URI pembatalan event juga harus 405,
     * bukan redirect autentikasi.
     */
    $eventCancellationResponse =
        $this->get(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
        );

    $eventCancellationResponse
        ->assertStatus(
            405,
        )
        ->assertHeader(
            'Allow',
        );

    $this->assertFalse(
        $eventCancellationResponse
            ->baseResponse
            ->isRedirection(),
    );

    /*
     * GET pada URI pembatalan siswa harus memiliki
     * perilaku yang sama.
     */
    $studentCancellationResponse =
        $this->get(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
        );

    $studentCancellationResponse
        ->assertStatus(
            405,
        )
        ->assertHeader(
            'Allow',
        );

    $this->assertFalse(
        $studentCancellationResponse
            ->baseResponse
            ->isRedirection(),
    );

    /*
     * Tidak boleh ada mutasi.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_options_requests_expose_only_route_methods_and_conceal_resource_existence(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'OPTIONS Route Discovery Contract',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $missingEventId =
        (int) $event->id
        + 999999;

    $missingEventStudentId =
        (int) $eventStudent->id
        + 999999;

    $normalizedAllowMethods =
        static function (
            mixed $response,
        ): array {
            return collect(
                explode(
                    ',',
                    (string) $response
                        ->headers
                        ->get(
                            'Allow',
                        ),
                ),
            )
                ->map(
                    static fn (
                        string $method,
                    ): string =>
                        trim(
                            $method,
                        ),
                )
                ->filter(
                    static fn (
                        string $method,
                    ): bool =>
                        $method !== '',
                )
                ->sort()
                ->values()
                ->all();
        };

    /*
     * Laravel menangani OPTIONS sebagai method discovery.
     * URI canonical menghasilkan 200 dengan body kosong dan
     * header Allow tanpa menjalankan autentikasi atau controller.
     *
     * Resource existing dan missing harus memiliki kontrak identik.
     */
    $detailTargets = [
        (int) $event->id,
        $missingEventId,
    ];

    foreach (
        $detailTargets as
        $detailTarget
    ) {
        $response =
            $this->call(
                'OPTIONS',
                sprintf(
                    '/gate/pickup-events/%d',
                    $detailTarget,
                ),
                [],
                [],
                [],
                [
                    'HTTP_ACCEPT' =>
                        'application/json',
                ],
            );

        $response
            ->assertOk()
            ->assertHeader(
                'Allow',
            );

        $this->assertSame(
            '',
            $response->getContent(),
        );

        $this->assertSame(
            [
                'GET',
                'HEAD',
            ],
            $normalizedAllowMethods(
                $response,
            ),
        );
    }

    /*
     * OPTIONS pada URI pembatalan event existing dan missing
     * hanya mengiklankan PATCH.
     */
    $eventCancellationTargets = [
        (int) $event->id,
        $missingEventId,
    ];

    foreach (
        $eventCancellationTargets as
        $eventCancellationTarget
    ) {
        $response =
            $this->call(
                'OPTIONS',
                sprintf(
                    '/gate/pickup-events/%d/cancel',
                    $eventCancellationTarget,
                ),
                [],
                [],
                [],
                [
                    'HTTP_ACCEPT' =>
                        'application/json',
                ],
            );

        $response
            ->assertOk()
            ->assertHeader(
                'Allow',
            );

        $this->assertSame(
            '',
            $response->getContent(),
        );

        $this->assertSame(
            [
                'PATCH',
            ],
            $normalizedAllowMethods(
                $response,
            ),
        );
    }

    /*
     * Seluruh kombinasi existing dan missing pada child route
     * harus menghasilkan kontrak method discovery yang sama.
     */
    $studentCancellationTargets = [
        [
            'event_id' =>
                (int) $event->id,

            'event_student_id' =>
                (int) $eventStudent->id,
        ],
        [
            'event_id' =>
                (int) $event->id,

            'event_student_id' =>
                $missingEventStudentId,
        ],
        [
            'event_id' =>
                $missingEventId,

            'event_student_id' =>
                (int) $eventStudent->id,
        ],
        [
            'event_id' =>
                $missingEventId,

            'event_student_id' =>
                $missingEventStudentId,
        ],
    ];

    foreach (
        $studentCancellationTargets as
        $studentCancellationTarget
    ) {
        $response =
            $this->call(
                'OPTIONS',
                sprintf(
                    '/gate/pickup-events/%d/students/%d/cancel',
                    $studentCancellationTarget[
                        'event_id'
                    ],
                    $studentCancellationTarget[
                        'event_student_id'
                    ],
                ),
                [],
                [],
                [],
                [
                    'HTTP_ACCEPT' =>
                        'application/json',
                ],
            );

        $response
            ->assertOk()
            ->assertHeader(
                'Allow',
            );

        $this->assertSame(
            '',
            $response->getContent(),
        );

        $this->assertSame(
            [
                'PATCH',
            ],
            $normalizedAllowMethods(
                $response,
            ),
        );
    }

    /*
     * OPTIONS tidak boleh menyebabkan mutasi.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_trace_and_options_on_malformed_routes_are_rejected_without_mutation(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'TRACE and Malformed OPTIONS Contract',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $normalizedAllowMethods =
        static function (
            mixed $response,
        ): array {
            return collect(
                explode(
                    ',',
                    (string) $response
                        ->headers
                        ->get(
                            'Allow',
                        ),
                ),
            )
                ->map(
                    static fn (
                        string $method,
                    ): string =>
                        trim(
                            $method,
                        ),
                )
                ->filter(
                    static fn (
                        string $method,
                    ): bool =>
                        $method !== '',
                )
                ->sort()
                ->values()
                ->all();
        };

    /*
     * TRACE pada URI canonical harus ditolak sebagai method
     * yang tidak diizinkan sebelum autentikasi dijalankan.
     */
    $detailTraceResponse =
        $this->call(
            'TRACE',
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' =>
                    'application/json',
            ],
        );

    $detailTraceResponse
        ->assertStatus(
            405,
        )
        ->assertHeader(
            'Allow',
        );

    $this->assertSame(
        [
            'GET',
            'HEAD',
        ],
        $normalizedAllowMethods(
            $detailTraceResponse,
        ),
    );

    $eventCancellationTraceResponse =
        $this->call(
            'TRACE',
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' =>
                    'application/json',
            ],
        );

    $eventCancellationTraceResponse
        ->assertStatus(
            405,
        )
        ->assertHeader(
            'Allow',
        );

    $this->assertSame(
        [
            'PATCH',
        ],
        $normalizedAllowMethods(
            $eventCancellationTraceResponse,
        ),
    );

    $studentCancellationTraceResponse =
        $this->call(
            'TRACE',
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' =>
                    'application/json',
            ],
        );

    $studentCancellationTraceResponse
        ->assertStatus(
            405,
        )
        ->assertHeader(
            'Allow',
        );

    $this->assertSame(
        [
            'PATCH',
        ],
        $normalizedAllowMethods(
            $studentCancellationTraceResponse,
        ),
    );

    /*
     * Identifier malformed tidak cocok dengan route.
     * OPTIONS dan TRACE harus menghasilkan 404, bukan 405.
     */
    $malformedEventIdentifiers = [
        'not-a-number',
        '0',
        sprintf(
            '0%d',
            $event->id,
        ),
        sprintf(
            '%d.0',
            $event->id,
        ),
        '9223372036854775808',
        str_repeat(
            '9',
            64,
        ),
    ];

    foreach (
        $malformedEventIdentifiers as
        $malformedEventIdentifier
    ) {
        $this
            ->call(
                'OPTIONS',
                sprintf(
                    '/gate/pickup-events/%s',
                    $malformedEventIdentifier,
                ),
                [],
                [],
                [],
                [
                    'HTTP_ACCEPT' =>
                        'application/json',
                ],
            )
            ->assertNotFound();

        $this
            ->call(
                'TRACE',
                sprintf(
                    '/gate/pickup-events/%s/cancel',
                    $malformedEventIdentifier,
                ),
                [],
                [],
                [],
                [
                    'HTTP_ACCEPT' =>
                        'application/json',
                ],
            )
            ->assertNotFound();
    }

    $malformedStudentIdentifiers = [
        'not-a-number',
        '0',
        sprintf(
            '0%d',
            $eventStudent->id,
        ),
        sprintf(
            '%d.0',
            $eventStudent->id,
        ),
        '9223372036854775808',
        str_repeat(
            '9',
            64,
        ),
    ];

    foreach (
        $malformedStudentIdentifiers as
        $malformedStudentIdentifier
    ) {
        $this
            ->call(
                'OPTIONS',
                sprintf(
                    '/gate/pickup-events/%d/students/%s/cancel',
                    $event->id,
                    $malformedStudentIdentifier,
                ),
                [],
                [],
                [],
                [
                    'HTTP_ACCEPT' =>
                        'application/json',
                ],
            )
            ->assertNotFound();

        $this
            ->call(
                'TRACE',
                sprintf(
                    '/gate/pickup-events/%d/students/%s/cancel',
                    $event->id,
                    $malformedStudentIdentifier,
                ),
                [],
                [],
                [],
                [
                    'HTTP_ACCEPT' =>
                        'application/json',
                ],
            )
            ->assertNotFound();
    }

    /*
     * Seluruh request harus nonmutating.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_authenticated_gate_history_and_detail_responses_are_not_cacheable(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Sensitive Cache-Control Contract',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $this->actingAs(
        $this->adminA,
    );

    /*
     * Halaman riwayat memuat informasi transaksi, siswa,
     * penjemput, petugas, dan status pembatalan.
     */
    $historyResponse =
        $this->get(
            '/gate/pickup-events',
        );

    $historyResponse
        ->assertOk();

    $this->assertSensitiveGateResponseIsNotCacheable(
        $historyResponse,
    );

    /*
     * Respons detail JSON juga memuat informasi transaksi
     * dan audit sehingga tidak boleh disimpan oleh cache.
     */
    $detailResponse =
        $this->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        );

    $detailResponse
        ->assertOk();

    $this->assertSensitiveGateResponseIsNotCacheable(
        $detailResponse,
    );

    /*
     * Pemeriksaan cache-control tidak boleh menyebabkan mutasi.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

public function test_successful_gate_cancellation_responses_are_not_cacheable(): void
{
    $wholeEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Whole Event No-Store Contract',
        );

    $studentEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Student Cancellation No-Store Contract',
        );

    $studentEventItem =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $studentEvent->id,
            )
            ->firstOrFail();

    $this->actingAs(
        $this->adminA,
    );

    /*
     * Respons pembatalan seluruh transaksi memuat audit baru
     * sehingga tidak boleh disimpan oleh cache.
     */
    $wholeEventResponse =
        $this->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $wholeEvent->id,
            ),
            [
                'reason' =>
                    'Koreksi transaksi untuk pengujian no-store',
            ],
        );

    $wholeEventResponse
        ->assertOk()
        ->assertJsonPath(
            'pickup_event.status',
            PickupEvent::STATUS_CANCELLED,
        );

    $this->assertSensitiveGateResponseIsNotCacheable(
        $wholeEventResponse,
    );

    /*
     * Respons pembatalan satu siswa juga memuat audit pembatalan.
     */
    $studentCancellationResponse =
        $this->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $studentEvent->id,
                $studentEventItem->id,
            ),
            [
                'reason' =>
                    'Koreksi siswa untuk pengujian no-store',
            ],
        );

    $studentCancellationResponse
        ->assertOk();

    $this->assertSensitiveGateResponseIsNotCacheable(
        $studentCancellationResponse,
    );

    $wholeEvent->refresh();
    $studentEvent->refresh();
    $studentEventItem->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CANCELLED,
        $wholeEvent->status,
    );

    $this->assertNotNull(
        $wholeEvent->cancelled_at,
    );

    $this->assertSame(
        (int) $this->adminA->id,
        (int) $wholeEvent->cancelled_by_user_id,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_CANCELLED,
        $studentEventItem->status,
    );

    $this->assertNotNull(
        $studentEventItem->cancelled_at,
    );

    $this->assertSame(
        (int) $this->adminA->id,
        (int) $studentEventItem->cancelled_by_user_id,
    );

    /*
     * Fixture hanya memiliki satu siswa sehingga pembatalan siswa
     * terakhir juga membuat parent masuk status cancelled.
     */
    $this->assertSame(
        PickupEvent::STATUS_CANCELLED,
        $studentEvent->status,
    );
}

public function test_authenticated_gate_not_found_responses_are_not_cacheable(): void
{
    $ownTenantEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Authenticated Not Found Cache Contract',
        );

    $ownTenantEventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $ownTenantEvent->id,
            )
            ->firstOrFail();

    $otherTenantEvent =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolB,

            officer:
                $this->officerTenantB,

            pickupPerson:
                $this->pickupPersonB,

            student:
                $this->studentB,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Other Tenant Not Found Cache Contract',
        );

    $missingEventId =
        (int) $ownTenantEvent->id
        + 999999;

    $missingEventStudentId =
        (int) $ownTenantEventStudent->id
        + 999999;

    $this->actingAs(
        $this->adminA,
    );

    /*
     * Identifier canonical yang tidak memiliki resource
     * mencapai route dan controller, kemudian menghasilkan 404.
     */
    $missingDetailResponse =
        $this->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $missingEventId,
            ),
        );

    $missingDetailResponse
        ->assertNotFound();

    $this->assertSensitiveGateResponseIsNotCacheable(
        $missingDetailResponse,
    );

    /*
     * Resource tenant lain disamarkan sebagai 404.
     * Respons concealment juga tidak boleh disimpan.
     */
    $otherTenantDetailResponse =
        $this->getJson(
            sprintf(
                '/gate/pickup-events/%d',
                $otherTenantEvent->id,
            ),
        );

    $otherTenantDetailResponse
        ->assertNotFound();

    $this->assertSensitiveGateResponseIsNotCacheable(
        $otherTenantDetailResponse,
    );

    /*
     * Pembatalan parent yang tidak ditemukan.
     */
    $missingCancellationResponse =
        $this->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $missingEventId,
            ),
            [
                'reason' =>
                    'Resource canonical tidak ditemukan',
            ],
        );

    $missingCancellationResponse
        ->assertNotFound();

    $this->assertSensitiveGateResponseIsNotCacheable(
        $missingCancellationResponse,
    );

    /*
     * Child canonical yang tidak ditemukan di dalam parent.
     */
    $missingStudentResponse =
        $this->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $ownTenantEvent->id,
                $missingEventStudentId,
            ),
            [
                'reason' =>
                    'Child canonical tidak ditemukan',
            ],
        );

    $missingStudentResponse
        ->assertNotFound();

    $this->assertSensitiveGateResponseIsNotCacheable(
        $missingStudentResponse,
    );

    /*
     * Seluruh request harus nonmutating.
     */
    $ownTenantEvent->refresh();
    $ownTenantEventStudent->refresh();
    $otherTenantEvent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $ownTenantEvent->status,
    );

    $this->assertNull(
        $ownTenantEvent->cancelled_at,
    );

    $this->assertNull(
        $ownTenantEvent->cancelled_by_user_id,
    );

    $this->assertNull(
        $ownTenantEvent->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $ownTenantEventStudent->status,
    );

    $this->assertNull(
        $ownTenantEventStudent->cancelled_at,
    );

    $this->assertNull(
        $ownTenantEventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $ownTenantEventStudent->cancellation_reason,
    );

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $otherTenantEvent->status,
    );

    $this->assertNull(
        $otherTenantEvent->cancelled_at,
    );

    $this->assertNull(
        $otherTenantEvent->cancelled_by_user_id,
    );

    $this->assertNull(
        $otherTenantEvent->cancellation_reason,
    );
}

public function test_authenticated_gate_validation_error_responses_are_not_cacheable(): void
{
    $event =
        $this->createHistoryFixtureEvent(
            school:
                $this->schoolA,

            officer:
                $this->officerA,

            pickupPerson:
                $this->pickupPersonA,

            student:
                $this->studentA,

            confirmedAt:
                CarbonImmutable::now(),

            pickupPersonName:
                'Validation Error Cache Contract',
        );

    $eventStudent =
        PickupEventStudent::query()
            ->where(
                'pickup_event_id',
                $event->id,
            )
            ->firstOrFail();

    $this->actingAs(
        $this->adminA,
    );

    /*
     * Reason wajib tersedia.
     * Error validasi dapat memuat nama field dan informasi request,
     * sehingga tidak boleh disimpan.
     */
    $wholeEventValidationResponse =
        $this->patchJson(
            sprintf(
                '/gate/pickup-events/%d/cancel',
                $event->id,
            ),
            [],
        );

    $wholeEventValidationResponse
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'reason',
        ]);

    $this->assertSensitiveGateResponseIsNotCacheable(
        $wholeEventValidationResponse,
    );

    $studentValidationResponse =
        $this->patchJson(
            sprintf(
                '/gate/pickup-events/%d/students/%d/cancel',
                $event->id,
                $eventStudent->id,
            ),
            [],
        );

    $studentValidationResponse
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'reason',
        ]);

    $this->assertSensitiveGateResponseIsNotCacheable(
        $studentValidationResponse,
    );

    /*
     * Validasi gagal tidak boleh mengubah audit.
     */
    $event->refresh();
    $eventStudent->refresh();

    $this->assertSame(
        PickupEvent::STATUS_CONFIRMED,
        $event->status,
    );

    $this->assertNull(
        $event->cancelled_at,
    );

    $this->assertNull(
        $event->cancelled_by_user_id,
    );

    $this->assertNull(
        $event->cancellation_reason,
    );

    $this->assertSame(
        PickupEventStudent::STATUS_RELEASED,
        $eventStudent->status,
    );

    $this->assertNull(
        $eventStudent->cancelled_at,
    );

    $this->assertNull(
        $eventStudent->cancelled_by_user_id,
    );

    $this->assertNull(
        $eventStudent->cancellation_reason,
    );
}

private function assertSensitiveGateResponseIsNotCacheable(
    \Illuminate\Testing\TestResponse $response,
): void {
    $cacheControl =
        strtolower(
            (string) $response
                ->headers
                ->get(
                    'Cache-Control',
                ),
        );

    $this->assertNotSame(
        '',
        trim(
            $cacheControl,
        ),
        'Respons sensitif wajib memiliki header Cache-Control.',
    );

    foreach (
        [
            'private',
            'no-store',
            'no-cache',
            'max-age=0',
            'must-revalidate',
        ] as $expectedDirective
    ) {
        $this->assertStringContainsString(
            $expectedDirective,
            $cacheControl,
            sprintf(
                'Cache-Control wajib memuat directive [%s]. Nilai aktual: [%s].',
                $expectedDirective,
                $cacheControl,
            ),
        );
    }

    $this->assertSame(
        'no-cache',
        strtolower(
            trim(
                (string) $response
                    ->headers
                    ->get(
                        'Pragma',
                    ),
            ),
        ),
        'Respons sensitif wajib memiliki Pragma: no-cache.',
    );

    $this->assertSame(
        '0',
        trim(
            (string) $response
                ->headers
                ->get(
                    'Expires',
                ),
        ),
        'Respons sensitif wajib memiliki Expires: 0.',
    );
}

private function confirmationPayload(
        PickupPersonFaceVerificationAttempt $attempt,
        string $idempotencyKey,
        string $notes,
    ): array {
        return [
            'idempotency_key' =>
                $idempotencyKey,

            'face_verification_attempt_id' =>
                (int) $attempt->id,

            'student_ids' => [
                (int) $this->studentA->id,
            ],

            'notes' =>
                $notes,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Verification attempt fixture
    |--------------------------------------------------------------------------
    */

    private function createMatchAttempt(
    School $school,
    User $verifiedBy,
    PickupPerson $pickupPerson,
    mixed $occurredAt = null,
    array $metadata = [],
): PickupPersonFaceVerificationAttempt {
    $attempt =
        new PickupPersonFaceVerificationAttempt();

    $attempt->forceFill([
        'school_id' =>
            $school->id,

        'pickup_person_id' =>
            $pickupPerson->id,

        'verified_by_user_id' =>
            $verifiedBy->id,

        'result' =>
            PickupPersonFaceVerificationAttempt::RESULT_MATCH,

        'similarity_score' =>
            0.91,

        'similarity_threshold' =>
            0.60,

        'candidate_margin' =>
            0.20,

        'candidate_count' =>
            1,

        'quality_score' =>
            0.90,

        'liveness_passed' =>
            true,

        'live_score' =>
            0.95,

        'real_score' =>
            0.90,

        'model_name' =>
            (string) config(
                'biometrics.model_name',
                'human-hse-faceres',
            ),

        'model_version' =>
            'test',

        'embedding_dimension' =>
            1024,

        'capture_method' =>
            'camera',

        'metadata' =>
            $metadata,

        'occurred_at' =>
            $occurredAt ?? now(),
    ]);

    $attempt->save();

    return $attempt->refresh();
}

    /*
    |--------------------------------------------------------------------------
    | Pickup event fixture
    |--------------------------------------------------------------------------
    */

    private function createConfirmedEvent(
        School $school,
        User $confirmedBy,
        mixed $confirmedAt,
        bool $withStudent = false,
    ): PickupEvent {
        $event =
            new PickupEvent();

        $event->forceFill([
            'school_id' =>
                $school->id,

            'pickup_person_id' =>
                null,

            'face_verification_attempt_id' =>
                null,

            'confirmed_by_user_id' =>
                $confirmedBy->id,

            'cancelled_by_user_id' =>
                null,

            'idempotency_key' =>
                (string) Str::uuid(),

            'verification_method' =>
                PickupEvent::VERIFICATION_METHOD_MANUAL,

            'status' =>
                PickupEvent::STATUS_CONFIRMED,

            'pickup_person_name' =>
                'Penjemput Pengujian',

            'pickup_person_phone' =>
                '080000000000',

            'verification_result' =>
                PickupPersonFaceVerificationAttempt::RESULT_MATCH,

            'similarity_score' =>
                null,

            'similarity_threshold' =>
                null,

            'candidate_margin' =>
                null,

            'confirmed_at' =>
                $confirmedAt,

            'cancelled_at' =>
                null,

            'cancellation_reason' =>
                null,

            'notes' =>
                'Data pengujian otomatis',

            'metadata' => [
                'source' =>
                    'automated_feature_test',
            ],
        ]);

        $event->save();

        if ($withStudent) {
            $eventStudent =
                new PickupEventStudent();

            $eventStudent->forceFill([
                'pickup_event_id' =>
                    $event->id,

                'student_id' =>
                    $school->is(
                        $this->schoolA,
                    )
                        ? $this->studentA->id
                        : null,

                'student_name' =>
                    'Siswa Pengujian',

                'student_number' =>
                    'TEST-001',

                'class_name' =>
                    'Kelas Pengujian',

                'academic_year' =>
                    '2026/2027',

                'relationship_type' =>
                    'guardian',

                'is_primary' =>
                    true,

                'status' =>
                    PickupEventStudent::STATUS_RELEASED,

                'released_at' =>
                    $confirmedAt,

                'cancelled_at' =>
                    null,

                'cancelled_by_user_id' =>
                    null,

                'cancellation_reason' =>
                    null,
            ]);

            $eventStudent->save();
        }

        return $event->refresh();
    }

    /*
    |--------------------------------------------------------------------------
    | School fixture
    |--------------------------------------------------------------------------
    */

    private function createSchool(
        string $name,
    ): School {
        $suffix =
            Str::lower(
                Str::random(12),
            );

        $school =
            new School();

        $school->forceFill(
            $this->fixtureAttributes(
                'schools',
                [
                    'name' =>
                        $name,

                    'slug' =>
                        Str::slug(
                            "{$name}-{$suffix}",
                        ),

                    'code' =>
                        Str::upper(
                            Str::random(10),
                        ),

                    'npsn' =>
                        (string) random_int(
                            10000000,
                            99999999,
                        ),

                    'email' =>
                        "school-{$suffix}@schoolsafe.test",

                    'phone' =>
                        sprintf(
                            '08%d',
                            random_int(
                                1000000000,
                                9999999999,
                            ),
                        ),

                    'address' =>
                        'Alamat sekolah pengujian otomatis',

                    'timezone' =>
                        'Asia/Jakarta',

                    'is_active' =>
                        true,
                ],
                "school-{$suffix}",
            ),
        );

        $school->save();

        return $school->refresh();
    }

    /*
    |--------------------------------------------------------------------------
    | User fixture
    |--------------------------------------------------------------------------
    */

    private function createUser(
        School $school,
        string $role,
        string $emailPrefix,
    ): User {
        $suffix =
            Str::lower(
                Str::random(12),
            );

        $user =
            new User();

        $user->forceFill(
            $this->fixtureAttributes(
                'users',
                [
                    'school_id' =>
                        $school->id,

                    'name' =>
                        Str::headline(
                            $emailPrefix,
                        ),

                    'email' =>
                        sprintf(
                            '%s-%s@schoolsafe.test',
                            Str::slug(
                                $emailPrefix,
                            ),
                            $suffix,
                        ),

                    'password' =>
                        Hash::make(
                            'TestPassword123!',
                        ),

                    'role' =>
                        $role,

                    'is_active' =>
                        true,

                    'email_verified_at' =>
                        now(),
                ],
                "user-{$emailPrefix}-{$suffix}",
            ),
        );

        $user->save();

        return $user->refresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Pickup person fixture
    |--------------------------------------------------------------------------
    */

    private function createPickupPerson(
        School $school,
    ): PickupPerson {
        $suffix =
            Str::lower(
                Str::random(12),
            );

        $pickupPerson =
            new PickupPerson();

        $pickupPerson->forceFill(
            $this->fixtureAttributes(
                'pickup_persons',
                [
                    'school_id' =>
                        $school->id,

                    'full_name' =>
                        sprintf(
                            'Penjemput Feature Test %s',
                            Str::upper(
                                Str::random(6),
                            ),
                        ),

                    'phone' =>
                        sprintf(
                            '08%d',
                            random_int(
                                1000000000,
                                9999999999,
                            ),
                        ),

                    'email' =>
                        "pickup-{$suffix}@schoolsafe.test",

                    'identity_number' =>
                        Str::upper(
                            "ID-{$suffix}",
                        ),

                    'address' =>
                        'Alamat penjemput pengujian otomatis',

                    'face_status' =>
                        PickupPerson::FACE_REGISTERED,

                    'is_active' =>
                        true,
                ],
                "pickup-person-{$suffix}",
            ),
        );

        $pickupPerson->save();

        return $pickupPerson->refresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Student fixture
    |--------------------------------------------------------------------------
    */

    private function createStudent(
    School $school,
): Student {
    $suffix =
        Str::lower(
            Str::random(12),
        );

    $schoolClassId =
        $this->createSchoolClassId(
            $school,
        );

    $preferred = [
        'school_id' =>
            $school->id,

        'full_name' =>
            sprintf(
                'Siswa Feature Test %s',
                Str::upper(
                    Str::random(6),
                ),
            ),

        'student_number' =>
            Str::upper(
                "TEST-{$suffix}",
            ),

        'nisn' =>
            (string) random_int(
                1000000000,
                9999999999,
            ),

        /*
         * Nilai menyesuaikan definisi kolom gender
         * pada database testing.
         */
        'gender' =>
            $this->studentGenderFixtureValue(),

        'date_of_birth' =>
            now()
                ->subYears(10)
                ->toDateString(),

        'birth_date' =>
            now()
                ->subYears(10)
                ->toDateString(),

        'address' =>
            'Alamat siswa pengujian otomatis',

        'status' =>
            Student::STATUS_ACTIVE,
    ];

    if ($schoolClassId !== null) {
        $preferred[
            'school_class_id'
        ] = $schoolClassId;

        $preferred[
            'class_id'
        ] = $schoolClassId;
    }

    $student =
        new Student();

    $student->forceFill(
        $this->fixtureAttributes(
            'students',
            $preferred,
            "student-{$suffix}",
        ),
    );

    $student->save();

    return $student->refresh();
}

    /*
    |--------------------------------------------------------------------------
    | School class fixture
    |--------------------------------------------------------------------------
    */

    private function createSchoolClassId(
        School $school,
    ): ?int {
        if (
            ! Schema::hasTable(
                'school_classes',
            )
        ) {
            return null;
        }

        $suffix =
            Str::lower(
                Str::random(10),
            );

        $className =
            sprintf(
                'Kelas Feature Test %s',
                Str::upper(
                    $suffix,
                ),
            );

        return (int) DB::table(
            'school_classes',
        )->insertGetId(
            $this->fixtureAttributes(
                'school_classes',
                [
                    'school_id' =>
                        $school->id,

                    'name' =>
                            $className,

                    'class_name' =>
                            $className,

                    'code' =>
                        Str::upper(
                            "CLS-{$suffix}",
                        ),

                    'grade_level' =>
                        5,

                    'academic_year' =>
                        '2026/2027',

                    'is_active' =>
                        true,
                ],
                "school-class-{$suffix}",
            ),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pickup authorization pivot
    |--------------------------------------------------------------------------
    */

    private function authorizePickupPersonForStudent(
        School $school,
        PickupPerson $pickupPerson,
        Student $student,
    ): void {
        DB::table(
            'pickup_person_student',
        )->insert(
            $this->fixtureAttributes(
                'pickup_person_student',
                [
                    'school_id' =>
                        $school->id,

                    'pickup_person_id' =>
                        $pickupPerson->id,

                    'student_id' =>
                        $student->id,

                    'relationship_type' =>
                        'guardian',

                    'is_primary' =>
                        true,

                    'is_active' =>
                        true,

                    'valid_from' =>
                        now()
                            ->subDay()
                            ->toDateString(),

                    'valid_until' =>
                        now()
                            ->addYear()
                            ->toDateString(),
                ],
                'pickup-person-student',
            ),
        );
    }

private function registerSessionProbeRoute(): void
{
    if (
        Route::has(
            'tests.session-probe',
        )
    ) {
        return;
    }

    Route::middleware(
        'web',
    )
        ->get(
            '/__tests/session-probe',
            static function (
                \Illuminate\Http\Request $request,
            ): \Illuminate\Http\JsonResponse {
                /*
                 * Digunakan untuk membuat session browser kedua
                 * pada pengujian lintas sesi.
                 */
                if (
                    $request->boolean(
                        'regenerate',
                    )
                ) {
                    $request
                        ->session()
                        ->regenerate(
                            true,
                        );
                }

                return response()->json([
                    'session_id' =>
                        $request
                            ->session()
                            ->getId(),
                ]);
            },
        )
        ->name(
            'tests.session-probe',
        );
}

/**
 * @return array{
 *     session_id: string,
 *     cookie_name: string,
 *     cookie_value: string
 * }
 */
private function establishAuthenticatedBrowserSession(
    User $user,
): array {
    $response =
        $this
            ->actingAs(
                $user,
            )
            ->getJson(
                '/__tests/session-probe',
            );

    $response->assertOk();

    return $this->browserSessionState(
        $response,
    );
}

/**
 * @param array{
 *     session_id: string,
 *     cookie_name: string,
 *     cookie_value: string
 * } $currentSession
 *
 * @return array{
 *     session_id: string,
 *     cookie_name: string,
 *     cookie_value: string
 * }
 */
private function regenerateBrowserSession(
    array $currentSession,
): array {
    $response =
    $this
        ->withCredentials()
        ->withUnencryptedCookie(
            $currentSession[
                'cookie_name'
            ],
            $currentSession[
                'cookie_value'
            ],
        )
        ->getJson(
            '/__tests/session-probe?regenerate=1',
        );

    $response->assertOk();

    return $this->browserSessionState(
        $response,
    );
}

/**
 * @return array{
 *     session_id: string,
 *     cookie_name: string,
 *     cookie_value: string
 * }
 */
private function browserSessionState(
    TestResponse $response,
): array {
    $sessionId =
        trim(
            (string) $response->json(
                'session_id',
            ),
        );

    if ($sessionId === '') {
        throw new RuntimeException(
            'Session ID aktual tidak ditemukan pada response session probe.',
        );
    }

    $cookieName =
        trim(
            (string) config(
                'session.cookie',
                '',
            ),
        );

    if ($cookieName === '') {
        throw new RuntimeException(
            'Nama cookie session Laravel tidak tersedia.',
        );
    }

    $sessionCookie =
        collect(
            $response
                ->headers
                ->getCookies(),
        )
            ->first(
                static fn (
                    \Symfony\Component\HttpFoundation\Cookie $cookie,
                ): bool =>
                    $cookie->getName()
                        === $cookieName,
            );

    if (
        ! $sessionCookie
            instanceof \Symfony\Component\HttpFoundation\Cookie
    ) {
        throw new RuntimeException(
            sprintf(
                'Cookie session [%s] tidak ditemukan pada response.',
                $cookieName,
            ),
        );
    }

    $cookieValue =
        trim(
            $sessionCookie->getValue(),
        );

    if ($cookieValue === '') {
        throw new RuntimeException(
            'Nilai cookie session Laravel kosong.',
        );
    }

    return [
        'session_id' =>
            $sessionId,

        /*
         * Cookie ini sudah dienkripsi oleh middleware
         * EncryptCookies. Karena itu request berikutnya harus
         * memakai withUnencryptedCookie agar nilainya tidak
         * dienkripsi untuk kedua kalinya.
         */
        'cookie_name' =>
            $cookieName,

        'cookie_value' =>
            $cookieValue,
    ];
}

private function sessionBindingForTest(
    string $sessionId,
    School $school,
    User $user,
): string {
    $applicationKey =
        trim(
            (string) config(
                'app.key',
                '',
            ),
        );

    $hashKey =
        $applicationKey !== ''
            ? $applicationKey
            : 'schoolsafe-session-binding';

    return hash_hmac(
        'sha256',
        implode(
            '|',
            [
                trim(
                    $sessionId,
                ),

                (string) $school->id,

                (string) $user->id,
            ],
        ),
        $hashKey,
    );
}


private function createHistoryFixtureEvent(
    School $school,
    User $officer,
    PickupPerson $pickupPerson,
    Student $student,
    string $status = PickupEvent::STATUS_CONFIRMED,
    string $verificationMethod = PickupEvent::VERIFICATION_METHOD_FACE,
    ?CarbonImmutable $confirmedAt = null,
    ?string $pickupPersonName = null,
    ?string $notes = null,
): PickupEvent {
    /*
     * Semua sumber fixture wajib berasal dari tenant yang sama.
     * Guard ini mencegah test history memberikan hasil palsu karena
     * kombinasi school, officer, pickup person, atau student lintas tenant.
     */
    $fixtureSchoolIds = [
        'officer' =>
            (int) $officer->school_id,

        'pickup_person' =>
            (int) $pickupPerson->school_id,

        'student' =>
            (int) $student->school_id,
    ];

    foreach ($fixtureSchoolIds as $fixtureName => $fixtureSchoolId) {
        if ($fixtureSchoolId !== (int) $school->id) {
            throw new RuntimeException(
                sprintf(
                    'Fixture history [%s] berasal dari school_id [%d], tetapi event ditujukan untuk school_id [%d].',
                    $fixtureName,
                    $fixtureSchoolId,
                    (int) $school->id,
                ),
            );
        }
    }

    $confirmedAt ??=
        CarbonImmutable::now();

    $isCancelled =
        $status
        === PickupEvent::STATUS_CANCELLED;

    $cancelledAt =
        $isCancelled
            ? $confirmedAt->addMinute()
            : null;

    $attempt =
        $verificationMethod
        === PickupEvent::VERIFICATION_METHOD_FACE
            ? $this->createMatchAttempt(
                school:
                    $school,

                verifiedBy:
                    $officer,

                pickupPerson:
                    $pickupPerson,

                occurredAt:
                    $confirmedAt,
            )
            : null;

    $event =
        new PickupEvent();

    $event->forceFill([
        'school_id' =>
            $school->id,

        'pickup_person_id' =>
            $pickupPerson->id,

        'face_verification_attempt_id' =>
            $attempt?->id,

        'confirmed_by_user_id' =>
            $officer->id,

        'cancelled_by_user_id' =>
            $isCancelled
                ? $officer->id
                : null,

        'idempotency_key' =>
            (string) Str::uuid(),

        'verification_method' =>
            $verificationMethod,

        'status' =>
            $status,

        'pickup_person_name' =>
            $pickupPersonName
            ?? (string) $pickupPerson->full_name,

        'pickup_person_phone' =>
            $pickupPerson->phone,

        'verification_result' =>
            $attempt?->result
            ?? PickupPersonFaceVerificationAttempt::RESULT_MATCH,

        'similarity_score' =>
            $attempt?->similarity_score,

        'similarity_threshold' =>
            $attempt?->similarity_threshold,

        'candidate_margin' =>
            $attempt?->candidate_margin,

        'confirmed_at' =>
            $confirmedAt,

        'cancelled_at' =>
            $cancelledAt,

        'cancellation_reason' =>
            $isCancelled
                ? 'History fixture cancellation'
                : null,

        'notes' =>
            $notes,

        'ip_address' =>
            '127.0.0.1',

        'user_agent' =>
            'SchoolSafe History Filter Test',

        'metadata' => [
            'source' =>
                'history_filter_test',

            'fixture_token' =>
                (string) Str::uuid(),
        ],
    ]);

    $event->save();

    $authorizedRelationship =
        DB::table(
            'pickup_person_student',
        )
            ->where(
                'school_id',
                $school->id,
            )
            ->where(
                'pickup_person_id',
                $pickupPerson->id,
            )
            ->where(
                'student_id',
                $student->id,
            )
            ->first();

    $studentStatus =
        $isCancelled
            ? PickupEventStudent::STATUS_CANCELLED
            : PickupEventStudent::STATUS_RELEASED;

    $event
        ->eventStudents()
        ->create([
            'student_id' =>
                $student->id,

            'student_name' =>
                $student->full_name,

            'student_number' =>
                $student->student_number,

            'class_name' =>
                null,

            'academic_year' =>
                null,

            'relationship_type' =>
                $authorizedRelationship
                    ?->relationship_type,

            'is_primary' =>
                (bool) (
                    $authorizedRelationship
                        ?->is_primary
                    ?? false
                ),

            'status' =>
                $studentStatus,

            'released_at' =>
                $confirmedAt,

            'cancelled_at' =>
                $cancelledAt,

            'cancelled_by_user_id' =>
                $isCancelled
                    ? $officer->id
                    : null,

            'cancellation_reason' =>
                $isCancelled
                    ? 'History fixture cancellation'
                    : null,
        ]);

    return $event->refresh();
}

private function addReleasedStudentToHistoryEvent(
    PickupEvent $event,
    School $school,
    PickupPerson $pickupPerson,
    Student $student,
): PickupEventStudent {
    $this->assertSame(
        (int) $school->id,
        (int) $event->school_id,
        'Event dan sekolah fixture harus berasal dari tenant yang sama.',
    );

    $this->assertSame(
        (int) $school->id,
        (int) $pickupPerson->school_id,
        'Penjemput fixture harus berasal dari sekolah event.',
    );

    $this->assertSame(
        (int) $school->id,
        (int) $student->school_id,
        'Siswa fixture harus berasal dari sekolah event.',
    );

    $this->assertSame(
        (int) $pickupPerson->id,
        (int) $event->pickup_person_id,
        'Penjemput fixture harus sama dengan penjemput snapshot event.',
    );

    /*
     * Buat authorization aktif agar snapshot relationship
     * mempunyai sumber data yang valid.
     */
    $this->authorizePickupPersonForStudent(
        $school,
        $pickupPerson,
        $student,
    );

    $authorizedRelationship =
        DB::table(
            'pickup_person_student',
        )
            ->where(
                'school_id',
                $school->id,
            )
            ->where(
                'pickup_person_id',
                $pickupPerson->id,
            )
            ->where(
                'student_id',
                $student->id,
            )
            ->first();

    $this->assertNotNull(
        $authorizedRelationship,
        'Authorization penjemput dan siswa gagal dibuat.',
    );

    $releasedAt =
        $event->confirmed_at
        ?? CarbonImmutable::now();

    $eventStudent =
        $event
            ->eventStudents()
            ->create([
                'student_id' =>
                    $student->id,

                'student_name' =>
                    $student->full_name,

                'student_number' =>
                    $student->student_number,

                'class_name' =>
                    null,

                'academic_year' =>
                    null,

                'relationship_type' =>
                    $authorizedRelationship
                        ->relationship_type,

                'is_primary' =>
                    (bool) $authorizedRelationship
                        ->is_primary,

                'status' =>
                    PickupEventStudent::STATUS_RELEASED,

                'released_at' =>
                    $releasedAt,

                'cancelled_at' =>
                    null,

                'cancelled_by_user_id' =>
                    null,

                'cancellation_reason' =>
                    null,
            ]);

    return $eventStudent->refresh();
}

private function normalizeInertiaValue(
    mixed $value,
): mixed {
    if ($value instanceof Collection) {
        $value =
            $value->all();
    }

    if (! is_array($value)) {
        return $value;
    }

    foreach (
        $value as
        $key => $item
    ) {
        $value[$key] =
            $this->normalizeInertiaValue(
                $item,
            );
    }

    return $value;
}
    
/**
 * @param array<string, mixed> $payload
 * @param array<int, string> $expectedKeys
 */

private function assertExactArrayKeys(
    array $payload,
    array $expectedKeys,
    string $context,
): void {
    $actualKeys =
        array_keys(
            $payload,
        );

    sort(
        $actualKeys,
    );

    sort(
        $expectedKeys,
    );

    $this->assertSame(
        $expectedKeys,
        $actualKeys,
        sprintf(
            'Kontrak response [%s] berubah.',
            $context,
        ),
    );
}

    /*
    |--------------------------------------------------------------------------
    | Environment protection
    |--------------------------------------------------------------------------
    */

    private function guardTestingDatabase(): void
    {
        if (
            ! app()->environment(
                'testing',
            )
        ) {
            throw new RuntimeException(
                'GatePickupEventSecurityTest hanya boleh dijalankan pada APP_ENV=testing.',
            );
        }

        $databaseName =
            DB::connection()
                ->getDatabaseName();

        if (
            ! is_string(
                $databaseName,
            )
            || trim(
                $databaseName,
            ) === ''
            || ! str_ends_with(
                strtolower(
                    trim(
                        $databaseName,
                    ),
                ),
                '_test',
            )
        ) {
            throw new RuntimeException(
                sprintf(
                    'Database pengujian tidak aman. Database aktif: [%s]. Gunakan database yang namanya berakhiran _test.',
                    is_scalar(
                        $databaseName,
                    )
                        ? (string) $databaseName
                        : 'tidak diketahui',
                ),
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Migration protection
    |--------------------------------------------------------------------------
    */

    private function assertRequiredTablesExist(): void
    {
        $requiredTables = [
            'schools',
            'users',
            'school_classes',
            'students',
            'pickup_persons',
            'pickup_person_student',
            'pickup_person_face_verification_attempts',
            'pickup_events',
            'pickup_event_students',
        ];

        $missingTables =
            array_values(
                array_filter(
                    $requiredTables,
                    static fn (
                        string $table,
                    ): bool =>
                        ! Schema::hasTable(
                            $table,
                        ),
                ),
            );

        if ($missingTables !== []) {
            throw new RuntimeException(
                sprintf(
                    'Tabel database testing belum lengkap: %s. Jalankan php artisan migrate --env=testing.',
                    implode(
                        ', ',
                        $missingTables,
                    ),
                ),
            );
        }
    }

    private function studentGenderFixtureValue(): string
{
    /*
     * Nilai ini tidak akan digunakan apabila tabel students
     * tidak memiliki kolom gender karena fixtureAttributes()
     * akan membuang atribut yang tidak tersedia.
     */
    if (
        ! Schema::hasColumn(
            'students',
            'gender',
        )
    ) {
        return 'L';
    }

    /*
     * Project testing menggunakan MySQL. Untuk driver selain
     * MySQL, gunakan kode gender satu karakter yang aman.
     */
    if (
        DB::connection()
            ->getDriverName()
        !== 'mysql'
    ) {
        return 'L';
    }

    $databaseName =
        DB::connection()
            ->getDatabaseName();

    if (
        ! is_string(
            $databaseName,
        )
        || trim(
            $databaseName,
        ) === ''
    ) {
        return 'L';
    }

    $columnInformation =
        DB::selectOne(
            <<<'SQL'
                SELECT
                    COLUMN_TYPE AS column_type,
                    CHARACTER_MAXIMUM_LENGTH AS maximum_length
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
                LIMIT 1
            SQL,
            [
                $databaseName,
                'students',
                'gender',
            ],
        );

    if (
        ! is_object(
            $columnInformation,
        )
    ) {
        return 'L';
    }

    $columnType =
        strtolower(
            trim(
                (string) (
                    $columnInformation
                        ->column_type
                    ?? ''
                ),
            ),
        );

    /*
     * Jika gender berupa enum, gunakan nilai enum pertama.
     *
     * Contoh:
     * enum('L','P')       menghasilkan L
     * enum('male','female') menghasilkan male
     */
    $enumValue =
        $this->firstEnumValue(
            $columnType,
        );

    if ($enumValue !== null) {
        return $enumValue;
    }

    $maximumLength =
        (int) (
            $columnInformation
                ->maximum_length
            ?? 0
        );

    /*
     * Kolom CHAR(1) atau VARCHAR(1) umumnya menggunakan
     * kode L/P.
     */
    if ($maximumLength === 1) {
        return 'L';
    }

    /*
     * Untuk kolom string biasa dengan kapasitas lebih panjang.
     */
    return 'male';
}
    
    /*
    |--------------------------------------------------------------------------
    | Schema-aware fixture attributes
    |--------------------------------------------------------------------------
    */

    /**
     * @param array<string, mixed> $preferred
     *
     * @return array<string, mixed>
     */
    private function fixtureAttributes(
        string $table,
        array $preferred,
        string $prefix,
    ): array {
        $columns =
            collect(
                Schema::getColumns(
                    $table,
                ),
            )
                ->filter(
                    static fn (
                        mixed $column,
                    ): bool =>
                        is_array(
                            $column,
                        )
                        && isset(
                            $column['name'],
                        )
                        && is_string(
                            $column['name'],
                        ),
                )
                ->keyBy(
                    static fn (
                        array $column,
                    ): string =>
                        $column['name'],
                );

        if ($columns->isEmpty()) {
            throw new RuntimeException(
                "Metadata kolom tabel [{$table}] tidak dapat dibaca.",
            );
        }

        $attributes = [];

        /*
         * Hanya masukkan preferred attribute yang
         * benar-benar tersedia pada schema.
         */
        foreach (
            $preferred as
            $columnName => $value
        ) {
            if (
                $columns->has(
                    $columnName,
                )
            ) {
                $attributes[
                    $columnName
                ] = $value;
            }
        }

        /*
         * Isi kolom wajib yang belum diberikan.
         */
        foreach (
            $columns as
            $columnName => $column
        ) {
            if (
                array_key_exists(
                    $columnName,
                    $attributes,
                )
                || $this
                    ->columnCanBeOmitted(
                        $columnName,
                        $column,
                    )
            ) {
                continue;
            }

            /*
             * Foreign key wajib tidak boleh ditebak
             * karena dapat menghasilkan relasi tidak valid.
             */
            if (
                str_ends_with(
                    $columnName,
                    '_id',
                )
            ) {
                throw new RuntimeException(
                    sprintf(
                        'Fixture tabel [%s] belum menyediakan foreign key wajib [%s].',
                        $table,
                        $columnName,
                    ),
                );
            }

            $attributes[
                $columnName
            ] = $this->fallbackColumnValue(
                $table,
                $columnName,
                $column,
                $prefix,
            );
        }

        if (
            $columns->has(
                'created_at',
            )
            && ! array_key_exists(
                'created_at',
                $attributes,
            )
        ) {
            $attributes[
                'created_at'
            ] = now();
        }

        if (
            $columns->has(
                'updated_at',
            )
            && ! array_key_exists(
                'updated_at',
                $attributes,
            )
        ) {
            $attributes[
                'updated_at'
            ] = now();
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $column
     */
    private function columnCanBeOmitted(
        string $columnName,
        array $column,
    ): bool {
        if (
            $columnName === 'id'
            || $columnName === 'deleted_at'
        ) {
            return true;
        }

        if (
            (bool) (
                $column[
                    'auto_increment'
                ]
                ?? false
            )
        ) {
            return true;
        }

        if (
            (bool) (
                $column['nullable']
                ?? false
            )
        ) {
            return true;
        }

        if (
            array_key_exists(
                'default',
                $column,
            )
            && $column['default']
                !== null
        ) {
            return true;
        }

        $generation =
            $column['generation']
            ?? null;

        return (
            is_string(
                $generation,
            )
            && trim(
                $generation,
            ) !== ''
        );
    }

    /**
     * @param array<string, mixed> $column
     */
    private function fallbackColumnValue(
        string $table,
        string $columnName,
        array $column,
        string $prefix,
    ): mixed {
        $type =
            strtolower(
                (string) (
                    $column['type']
                    ?? $column['type_name']
                    ?? ''
                ),
            );

        if (
            str_starts_with(
                $type,
                'enum',
            )
        ) {
            $enumValue =
                $this->firstEnumValue(
                    $type,
                );

            if ($enumValue !== null) {
                return $enumValue;
            }
        }

        if (
            str_contains(
                $type,
                'bool',
            )
            || str_contains(
                $type,
                'tinyint',
            )
            || str_contains(
                $type,
                'bit',
            )
        ) {
            return false;
        }

        if (
            str_contains(
                $type,
                'int',
            )
        ) {
            return 1;
        }

        if (
            str_contains(
                $type,
                'decimal',
            )
            || str_contains(
                $type,
                'numeric',
            )
            || str_contains(
                $type,
                'float',
            )
            || str_contains(
                $type,
                'double',
            )
        ) {
            return 0;
        }

        if (
            str_contains(
                $type,
                'date',
            )
            && ! str_contains(
                $type,
                'time',
            )
        ) {
            return now()
                ->toDateString();
        }

        if (
            str_contains(
                $type,
                'time',
            )
        ) {
            return now();
        }

        if (
            str_contains(
                $type,
                'json',
            )
        ) {
            return [];
        }

        if (
            str_contains(
                $type,
                'binary',
            )
            || str_contains(
                $type,
                'blob',
            )
        ) {
            return '';
        }

        if (
            str_contains(
                $columnName,
                'email',
            )
        ) {
            return sprintf(
                '%s-%s@schoolsafe.test',
                Str::slug(
                    $prefix,
                ),
                Str::lower(
                    Str::random(8),
                ),
            );
        }

        if (
            str_contains(
                $columnName,
                'uuid',
            )
        ) {
            return (string) Str::uuid();
        }

        if (
            str_contains(
                $columnName,
                'phone',
            )
        ) {
            return sprintf(
                '08%d',
                random_int(
                    1000000000,
                    9999999999,
                ),
            );
        }

        if (
            str_contains(
                $columnName,
                'password',
            )
        ) {
            return Hash::make(
                'TestPassword123!',
            );
        }

        /*
         * Status wajib harus diberi secara eksplisit.
         * Jangan menebak nilai domain aplikasi.
         */
        if (
            str_contains(
                $columnName,
                'status',
            )
        ) {
            throw new RuntimeException(
                sprintf(
                    'Kolom status wajib [%s.%s] harus diberi nilai eksplisit pada fixture.',
                    $table,
                    $columnName,
                ),
            );
        }

        return Str::limit(
            sprintf(
                '%s-%s',
                Str::slug(
                    $prefix,
                ),
                Str::lower(
                    Str::random(10),
                ),
            ),
            40,
            '',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Enum helper
    |--------------------------------------------------------------------------
    */

    private function firstEnumValue(
        string $type,
    ): ?string {
        if (
            preg_match(
                "/^enum\\((.*)\\)$/i",
                $type,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        if (
            preg_match(
                "/'((?:[^'\\\\]|\\\\.)*)'/",
                $matches[1],
                $valueMatch,
            ) !== 1
        ) {
            return null;
        }

        return stripcslashes(
            $valueMatch[1],
        );
    }
}