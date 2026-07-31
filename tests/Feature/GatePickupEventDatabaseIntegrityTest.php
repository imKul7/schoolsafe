<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class GatePickupEventDatabaseIntegrityTest extends TestCase
{
    private string $databaseName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseName =
            trim(
                (string) DB::connection()
                    ->getDatabaseName(),
            );

        $this->guardTestingDatabase();

        foreach (
            [
                'pickup_events',
                'pickup_event_students',
                'pickup_person_face_verification_attempts',
                'schools',
                'users',
                'pickup_persons',
                'students',
            ] as $table
        ) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException(
                    sprintf(
                        'Tabel wajib [%s] tidak ditemukan pada database testing.',
                        $table,
                    ),
                );
            }
        }
    }

    #[Test]
    public function concurrency_tables_use_innodb_engine(): void
    {
        foreach (
            [
                'pickup_events',
                'pickup_event_students',
                'pickup_person_face_verification_attempts',
            ] as $table
        ) {
            $engine =
                DB::table('information_schema.TABLES')
                    ->where(
                        'TABLE_SCHEMA',
                        $this->databaseName,
                    )
                    ->where(
                        'TABLE_NAME',
                        $table,
                    )
                    ->value('ENGINE');

            $this->assertNotNull(
                $engine,
                sprintf(
                    'Metadata engine untuk tabel [%s] tidak ditemukan.',
                    $table,
                ),
            );

            $this->assertSame(
                'innodb',
                strtolower(
                    (string) $engine,
                ),
                sprintf(
                    'Tabel [%s] harus menggunakan InnoDB agar row lock dan transaksi bekerja.',
                    $table,
                ),
            );
        }
    }

    #[Test]
    public function pickup_events_has_unique_idempotency_key(): void
    {
        $this->assertUniqueIndexExists(
            table: 'pickup_events',

            columns: [
                'idempotency_key',
            ],
        );
    }

    #[Test]
    public function face_verification_attempt_can_only_be_used_once(): void
    {
        $this->assertUniqueIndexExists(
            table: 'pickup_events',

            columns: [
                'face_verification_attempt_id',
            ],
        );
    }

    #[Test]
    public function student_cannot_be_duplicated_inside_same_pickup_event(): void
    {
        $this->assertUniqueIndexExists(
            table: 'pickup_event_students',

            columns: [
                'pickup_event_id',
                'student_id',
            ],
        );
    }

    #[Test]
    public function pickup_event_student_has_parent_foreign_key(): void
    {
        $this->assertForeignKeyExists(
            table: 'pickup_event_students',

            column: 'pickup_event_id',

            referencedTable: 'pickup_events',

            referencedColumn: 'id',
        );
    }

    #[Test]
    public function pickup_event_has_school_foreign_key(): void
    {
        $this->assertForeignKeyExists(
            table: 'pickup_events',

            column: 'school_id',

            referencedTable: 'schools',

            referencedColumn: 'id',
        );
    }

    #[Test]
    public function pickup_event_has_pickup_person_foreign_key(): void
    {
        $this->assertForeignKeyExists(
            table: 'pickup_events',

            column: 'pickup_person_id',

            referencedTable: 'pickup_persons',

            referencedColumn: 'id',
        );
    }

    #[Test]
    public function pickup_event_has_face_attempt_foreign_key(): void
    {
        $this->assertForeignKeyExists(
            table: 'pickup_events',

            column: 'face_verification_attempt_id',

            referencedTable: 'pickup_person_face_verification_attempts',

            referencedColumn: 'id',
        );
    }

    #[Test]
    public function pickup_event_student_has_student_foreign_key(): void
    {
        $this->assertForeignKeyExists(
            table: 'pickup_event_students',

            column: 'student_id',

            referencedTable: 'students',

            referencedColumn: 'id',
        );
    }

    #[Test]
    public function pickup_events_has_tenant_history_index(): void
    {
        $this->assertIndexPrefixExists(
            table: 'pickup_events',

            requiredPrefix: [
                'school_id',
                'confirmed_at',
            ],
        );
    }

    #[Test]
    public function pickup_event_students_has_parent_status_index(): void
    {
        $this->assertIndexPrefixExists(
            table: 'pickup_event_students',

            requiredPrefix: [
                'pickup_event_id',
                'status',
            ],
        );
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function assertUniqueIndexExists(
        string $table,
        array $columns,
    ): void {
        $indexes =
            $this->indexesForTable(
                $table,
            );

        foreach ($indexes as $index) {
            if (
                (int) $index['non_unique'] === 0
                && $index['columns'] === $columns
            ) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail(
            sprintf(
                'Unique index [%s] tidak ditemukan pada tabel [%s]. Index aktual: %s',
                implode(', ', $columns),
                $table,
                json_encode(
                    $indexes,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE,
                ),
            ),
        );
    }

    /**
     * @param  array<int, string>  $requiredPrefix
     */
    private function assertIndexPrefixExists(
        string $table,
        array $requiredPrefix,
    ): void {
        $indexes =
            $this->indexesForTable(
                $table,
            );

        foreach ($indexes as $index) {
            $actualPrefix =
                array_slice(
                    $index['columns'],
                    0,
                    count($requiredPrefix),
                );

            if ($actualPrefix === $requiredPrefix) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail(
            sprintf(
                'Index dengan prefix [%s] tidak ditemukan pada tabel [%s]. Index aktual: %s',
                implode(', ', $requiredPrefix),
                $table,
                json_encode(
                    $indexes,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE,
                ),
            ),
        );
    }

    private function assertForeignKeyExists(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
    ): void {
        $constraint =
            DB::table(
                'information_schema.KEY_COLUMN_USAGE',
            )
                ->where(
                    'TABLE_SCHEMA',
                    $this->databaseName,
                )
                ->where(
                    'TABLE_NAME',
                    $table,
                )
                ->where(
                    'COLUMN_NAME',
                    $column,
                )
                ->where(
                    'REFERENCED_TABLE_NAME',
                    $referencedTable,
                )
                ->where(
                    'REFERENCED_COLUMN_NAME',
                    $referencedColumn,
                )
                ->first();

        $this->assertNotNull(
            $constraint,
            sprintf(
                'Foreign key [%s.%s -> %s.%s] tidak ditemukan.',
                $table,
                $column,
                $referencedTable,
                $referencedColumn,
            ),
        );
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     non_unique: int,
     *     columns: array<int, string>
     * }>
     */
    private function indexesForTable(
        string $table,
    ): array {
        $rows =
            DB::table(
                'information_schema.STATISTICS',
            )
                ->select([
                    'INDEX_NAME',
                    'NON_UNIQUE',
                    'COLUMN_NAME',
                    'SEQ_IN_INDEX',
                ])
                ->where(
                    'TABLE_SCHEMA',
                    $this->databaseName,
                )
                ->where(
                    'TABLE_NAME',
                    $table,
                )
                ->orderBy('INDEX_NAME')
                ->orderBy('SEQ_IN_INDEX')
                ->get();

        return $rows
            ->groupBy(
                static fn (
                    object $row,
                ): string => (string) $row->INDEX_NAME,
            )
            ->map(
                static function (
                    $group,
                    string $indexName,
                ): array {
                    return [
                        'name' => $indexName,

                        'non_unique' => (int) $group
                            ->first()
                            ->NON_UNIQUE,

                        'columns' => $group
                            ->pluck(
                                'COLUMN_NAME',
                            )
                            ->map(
                                static fn (
                                    mixed $column,
                                ): string => (string) $column,
                            )
                            ->values()
                            ->all(),
                    ];
                },
            )
            ->values()
            ->all();
    }

    private function guardTestingDatabase(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException(
                sprintf(
                    'Test integrity hanya boleh berjalan pada environment testing. Environment: %s',
                    app()->environment(),
                ),
            );
        }

        if (
            $this->databaseName === ''
            || ! str_ends_with(
                strtolower(
                    $this->databaseName,
                ),
                '_test',
            )
        ) {
            throw new RuntimeException(
                sprintf(
                    'Test integrity menolak database non-testing: %s',
                    $this->databaseName !== ''
                        ? $this->databaseName
                        : '[kosong]',
                ),
            );
        }
    }
}
