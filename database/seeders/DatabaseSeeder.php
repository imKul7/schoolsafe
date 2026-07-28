<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PickupPerson;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
{
    if (app()->environment('production')) {
        $this->command?->warn(
            'Demo seeder tidak dijalankan pada environment production.',
        );

        return;
    }

    DB::transaction(function (): void {
            $school = $this->createSchool();

            $this->createUsers($school);

            $students = $this->createClassesAndStudents(
                $school,
            );

            $this->createPickupPersons(
                $school,
                $students,
            );
        });
    }

    private function createSchool(): School
    {
        return School::updateOrCreate(
            [
                'code' => 'SCHOOLSAFE-DEMO',
            ],
            [
                'name' => 'Sekolah Dasar SchoolSafe',
                'npsn' => null,
                'email' => 'sekolah@schoolsafe.test',
                'phone' => '081234567890',
                'address' => 'Jl. Pendidikan No. 1',
                'city' => 'Depok',
                'province' => 'Jawa Barat',
                'logo_path' => null,
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ],
        );
    }

    private function createUsers(School $school): void
    {
        $defaultPassword = Hash::make(
            'SchoolSafe123!',
        );

        User::updateOrCreate(
            [
                'email' => 'admin@schoolsafe.test',
            ],
            [
                'school_id' => $school->id,
                'name' => 'Admin SchoolSafe',
                'role' => User::ROLE_SCHOOL_ADMIN,
                'phone' => '081234567890',
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => $defaultPassword,
            ],
        );

        User::updateOrCreate(
            [
                'email' => 'petugas@schoolsafe.test',
            ],
            [
                'school_id' => $school->id,
                'name' => 'Petugas Gerbang',
                'role' => User::ROLE_GATE_OFFICER,
                'phone' => '081234567891',
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => $defaultPassword,
            ],
        );

        User::updateOrCreate(
            [
                'email' => 'guru@schoolsafe.test',
            ],
            [
                'school_id' => $school->id,
                'name' => 'Guru SchoolSafe',
                'role' => User::ROLE_TEACHER,
                'phone' => '081234567892',
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => $defaultPassword,
            ],
        );
    }

    /**
     * @return array<string, Student>
     */
    private function createClassesAndStudents(
        School $school,
    ): array {
        $class3A = SchoolClass::updateOrCreate(
            [
                'school_id' => $school->id,
                'name' => '3A',
                'academic_year' => '2026/2027',
            ],
            [
                'grade_level' => 3,
                'homeroom_teacher' => 'Ibu Dian Puspita',
                'is_active' => true,
            ],
        );

        $class4B = SchoolClass::updateOrCreate(
            [
                'school_id' => $school->id,
                'name' => '4B',
                'academic_year' => '2026/2027',
            ],
            [
                'grade_level' => 4,
                'homeroom_teacher' => 'Bapak Arif Wijaya',
                'is_active' => true,
            ],
        );

        $class2C = SchoolClass::updateOrCreate(
            [
                'school_id' => $school->id,
                'name' => '2C',
                'academic_year' => '2026/2027',
            ],
            [
                'grade_level' => 2,
                'homeroom_teacher' => 'Ibu Nabila Putri',
                'is_active' => true,
            ],
        );

        $studentDefinitions = [
            [
                'student_number' => 'SS-0001',
                'school_class_id' => $class3A->id,
                'nisn' => '0012345678',
                'full_name' => 'Kayla Putri',
                'gender' => Student::GENDER_FEMALE,
                'date_of_birth' => '2017-05-12',
                'status' => Student::STATUS_ACTIVE,
                'notes' => null,
            ],
            [
                'student_number' => 'SS-0002',
                'school_class_id' => $class4B->id,
                'nisn' => '0012345679',
                'full_name' => 'Andi Pratama',
                'gender' => Student::GENDER_MALE,
                'date_of_birth' => '2016-08-21',
                'status' => Student::STATUS_ACTIVE,
                'notes' => null,
            ],
            [
                'student_number' => 'SS-0003',
                'school_class_id' => $class2C->id,
                'nisn' => '0012345680',
                'full_name' => 'Aulia Rahma',
                'gender' => Student::GENDER_FEMALE,
                'date_of_birth' => '2018-01-18',
                'status' => Student::STATUS_ACTIVE,
                'notes' => null,
            ],
            [
                'student_number' => 'SS-0004',
                'school_class_id' => $class3A->id,
                'nisn' => '0012345681',
                'full_name' => 'Dimas Saputra',
                'gender' => Student::GENDER_MALE,
                'date_of_birth' => '2017-09-03',
                'status' => Student::STATUS_ACTIVE,
                'notes' => null,
            ],
            [
                'student_number' => 'SS-0005',
                'school_class_id' => $class4B->id,
                'nisn' => '0012345682',
                'full_name' => 'Nadia Maharani',
                'gender' => Student::GENDER_FEMALE,
                'date_of_birth' => '2016-11-14',
                'status' => Student::STATUS_ACTIVE,
                'notes' => null,
            ],
        ];

        $students = [];

        foreach ($studentDefinitions as $definition) {
            $studentNumber = $definition['student_number'];

            $student = Student::withTrashed()
                ->updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'student_number' => $studentNumber,
                    ],
                    [
                        'school_class_id' =>
                            $definition['school_class_id'],

                        'nisn' =>
                            $definition['nisn'],

                        'full_name' =>
                            $definition['full_name'],

                        'gender' =>
                            $definition['gender'],

                        'date_of_birth' =>
                            $definition['date_of_birth'],

                        'photo_path' => null,

                        'status' =>
                            $definition['status'],

                        'notes' =>
                            $definition['notes'],
                    ],
                );

            if ($student->trashed()) {
                $student->restore();
            }

            $students[$studentNumber] = $student;
        }

        return $students;
    }

    /**
     * @param array<string, Student> $students
     */
    private function createPickupPersons(
        School $school,
        array $students,
    ): void {
        $budi = $this->upsertPickupPerson(
            $school,
            '3276010101800001',
            [
                'full_name' => 'Budi Pratama',
                'phone' => '081298765401',
                'email' => 'budi.pratama@schoolsafe.test',
                'address' => 'Jl. Melati No. 10, Depok',
                'photo_path' => null,
                'face_status' =>
                    PickupPerson::FACE_REGISTERED,
                'is_active' => true,
                'notes' => 'Ayah kandung Andi Pratama.',
            ],
        );

        $siti = $this->upsertPickupPerson(
            $school,
            '3276014502850002',
            [
                'full_name' => 'Siti Rahmawati',
                'phone' => '081298765402',
                'email' => 'siti.rahmawati@schoolsafe.test',
                'address' => 'Jl. Anggrek No. 5, Depok',
                'photo_path' => null,
                'face_status' =>
                    PickupPerson::FACE_REGISTERED,
                'is_active' => true,
                'notes' => 'Ibu kandung Aulia Rahma.',
            ],
        );

        $rina = $this->upsertPickupPerson(
            $school,
            '3276015502880003',
            [
                'full_name' => 'Rina Putri',
                'phone' => '081298765403',
                'email' => 'rina.putri@schoolsafe.test',
                'address' => 'Jl. Kenanga No. 7, Depok',
                'photo_path' => null,
                'face_status' =>
                    PickupPerson::FACE_NOT_REGISTERED,
                'is_active' => true,
                'notes' => 'Ibu kandung Kayla Putri.',
            ],
        );

        $dedi = $this->upsertPickupPerson(
            $school,
            '3276011502780004',
            [
                'full_name' => 'Dedi Setiawan',
                'phone' => '081298765404',
                'email' => 'dedi.setiawan@schoolsafe.test',
                'address' => 'Jl. Mawar No. 20, Depok',
                'photo_path' => null,
                'face_status' =>
                    PickupPerson::FACE_NEEDS_UPDATE,
                'is_active' => true,
                'notes' =>
                    'Pengemudi keluarga yang diizinkan menjemput Dimas dan Nadia.',
            ],
        );

        $validFrom = '2026-07-01';

        $budi->students()->syncWithoutDetaching([
            $students['SS-0002']->id => [
                'school_id' => $school->id,
                'relationship_type' => 'father',
                'is_primary' => true,
                'is_active' => true,
                'valid_from' => $validFrom,
                'valid_until' => null,
            ],
        ]);

        $siti->students()->syncWithoutDetaching([
            $students['SS-0003']->id => [
                'school_id' => $school->id,
                'relationship_type' => 'mother',
                'is_primary' => true,
                'is_active' => true,
                'valid_from' => $validFrom,
                'valid_until' => null,
            ],
        ]);

        $rina->students()->syncWithoutDetaching([
            $students['SS-0001']->id => [
                'school_id' => $school->id,
                'relationship_type' => 'mother',
                'is_primary' => true,
                'is_active' => true,
                'valid_from' => $validFrom,
                'valid_until' => null,
            ],
        ]);

        /*
         * Contoh satu penjemput dapat terhubung
         * dengan lebih dari satu siswa.
         */
        $dedi->students()->syncWithoutDetaching([
            $students['SS-0004']->id => [
                'school_id' => $school->id,
                'relationship_type' => 'driver',
                'is_primary' => false,
                'is_active' => true,
                'valid_from' => $validFrom,
                'valid_until' => null,
            ],

            $students['SS-0005']->id => [
                'school_id' => $school->id,
                'relationship_type' => 'driver',
                'is_primary' => false,
                'is_active' => true,
                'valid_from' => $validFrom,
                'valid_until' => null,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function upsertPickupPerson(
        School $school,
        string $identityNumber,
        array $attributes,
    ): PickupPerson {
        $pickupPerson = PickupPerson::withTrashed()
            ->updateOrCreate(
                [
                    'school_id' => $school->id,
                    'identity_number' => $identityNumber,
                ],
                $attributes,
            );

        if ($pickupPerson->trashed()) {
            $pickupPerson->restore();
        }

        return $pickupPerson;
    }
}