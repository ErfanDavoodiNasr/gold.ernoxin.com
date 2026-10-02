<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DatabaseMigrator
{
    public const SCHEMA_VERSION = 2;
    private const LOCK_TIMEOUT_SECONDS = 15;
    private const STATE_FILE = 'database-migrated.ts';
    private const LOCK_FILE = 'database-migration.lock';

    /**
     * Quickly checks whether the database has already been verified and migrated
     * to the current schema version without executing heavy database inspection.
     */
    public function isMigrated(): bool
    {
        $statePath = storage_path('framework/' . self::STATE_FILE);

        if (!is_file($statePath)) {
            return false;
        }

        $version = trim((string)@file_get_contents($statePath));

        return $version === (string)self::SCHEMA_VERSION;
    }

    /**
     * Ensures database is fully migrated and healthy. Thread-safe and process-safe.
     *
     * @return array{status:string,message:string,actions:array<string>,error?:string}
     */
    public function ensureMigrated(bool $force = false): array
    {
        if (!$force && $this->isMigrated()) {
            return [
                'status' => 'skipped',
                'message' => 'Database already migrated to schema v' . self::SCHEMA_VERSION . '.',
                'actions' => [],
            ];
        }

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            report($e);

            return [
                'status' => 'failed',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'actions' => [],
                'error' => $e->getMessage(),
            ];
        }

        $lockFp = null;
        $dbLocked = false;

        try {
            $lockPath = storage_path('framework/' . self::LOCK_FILE);
            $lockFp = @fopen($lockPath, 'c');

            if ($lockFp && !flock($lockFp, LOCK_EX | LOCK_NB)) {
                return [
                    'status' => 'busy',
                    'message' => 'Database migration is currently executing in another process.',
                    'actions' => [],
                ];
            }

            if (DB::connection()->getDriverName() === 'mysql') {
                $lockRes = DB::selectOne(
                    "SELECT GET_LOCK('gold_db_migration_lock', ?) AS locked",
                    [self::LOCK_TIMEOUT_SECONDS]
                );
                $dbLocked = (bool)($lockRes->locked ?? false);

                if (!$dbLocked) {
                    return [
                        'status' => 'busy',
                        'message' => 'Could not acquire database advisory lock within timeout.',
                        'actions' => [],
                    ];
                }
            }

            if (!$force && $this->isMigrated()) {
                return [
                    'status' => 'skipped',
                    'message' => 'Database was migrated by a concurrent worker.',
                    'actions' => [],
                ];
            }

            $result = $this->migrate(false);

            if ($result['status'] === 'success' || $result['status'] === 'skipped') {
                $this->markAsMigrated();
            }

            return $result;
        } catch (\Throwable $e) {
            report($e);
            Log::error('Database migration failed: ' . $e->getMessage(), ['exception' => $e]);

            return [
                'status' => 'failed',
                'message' => 'Migration failed: ' . $e->getMessage(),
                'actions' => [],
                'error' => $e->getMessage(),
            ];
        } finally {
            if ($dbLocked) {
                try {
                    DB::statement("SELECT RELEASE_LOCK('gold_db_migration_lock')");
                } catch (\Throwable) {
                    // Ignore release errors
                }
            }

            if ($lockFp) {
                @flock($lockFp, LOCK_UN);
                @fclose($lockFp);
            }
        }
    }

    /**
     * Inspects database schema and data health and returns a diagnostic snapshot.
     */
    public function diagnose(): array
    {
        $driver = DB::connection()->getDriverName();
        $hasPricePoints = Schema::hasTable('price_points');
        $hasHourly = Schema::hasTable('price_points_hourly');
        $hasMarketItems = Schema::hasTable('market_items');
        $hasFetchLogs = Schema::hasTable('fetch_logs');

        $pricePointsColumns = $hasPricePoints ? Schema::getColumnListing('price_points') : [];
        $hourlyColumns = $hasHourly ? Schema::getColumnListing('price_points_hourly') : [];

        $hasItemKey = in_array('item_key', $pricePointsColumns, true);
        $hasMarketItemId = in_array('market_item_id', $pricePointsColumns, true);
        $hasRawPayload = in_array('raw_payload', $pricePointsColumns, true);

        $nullItemKeysCount = 0;
        if ($hasPricePoints && $hasItemKey) {
            $nullItemKeysCount = DB::table('price_points')
                ->where(function ($q) {
                    $q->whereNull('item_key')->orWhere('item_key', '');
                })
                ->count();
        }

        $rawPayloadNullable = $hasRawPayload ? $this->isColumnNullable('price_points', 'raw_payload') : true;

        $indexes = [
            'price_points' => [
                'price_points_item_key_fetched_at_unique' => $hasPricePoints && $this->hasIndex('price_points', 'price_points_item_key_fetched_at_unique'),
                'price_points_fetched_at_index' => $hasPricePoints && $this->hasIndex('price_points', 'price_points_fetched_at_index'),
                'price_points_item_key_current_fetched_at_index' => $hasPricePoints && $this->hasIndex('price_points', 'price_points_item_key_current_fetched_at_index'),
                'price_points_market_item_id_fetched_at_unique' => $hasPricePoints && $this->hasIndex('price_points', 'price_points_market_item_id_fetched_at_unique'),
            ],
            'price_points_hourly' => [
                'price_points_hourly_item_bucket_unique' => $hasHourly && $this->hasIndex('price_points_hourly', 'price_points_hourly_item_bucket_unique'),
                'price_points_hourly_item_bucket_value_index' => $hasHourly && $this->hasIndex('price_points_hourly', 'price_points_hourly_item_bucket_value_index'),
            ],
        ];

        $pendingActions = $this->resolvePendingActions();

        return [
            'driver' => $driver,
            'target_schema_version' => self::SCHEMA_VERSION,
            'is_migrated_marker' => $this->isMigrated(),
            'tables' => [
                'price_points' => $hasPricePoints,
                'price_points_hourly' => $hasHourly,
                'market_items' => $hasMarketItems,
                'fetch_logs' => $hasFetchLogs,
            ],
            'columns' => [
                'price_points' => $pricePointsColumns,
                'price_points_hourly' => $hourlyColumns,
            ],
            'indexes' => $indexes,
            'legacy' => [
                'has_market_item_id' => $hasMarketItemId,
                'missing_item_key' => $hasPricePoints && !$hasItemKey,
                'null_item_keys_count' => $nullItemKeysCount,
                'raw_payload_exists' => $hasRawPayload,
                'raw_payload_nullable' => $rawPayloadNullable,
                'has_legacy_fk' => $this->hasForeignKey('price_points', 'price_points_market_item_id_foreign'),
            ],
            'counts' => [
                'price_points' => $hasPricePoints ? DB::table('price_points')->count() : 0,
                'price_points_hourly' => $hasHourly ? DB::table('price_points_hourly')->count() : 0,
            ],
            'pending_actions' => $pendingActions,
        ];
    }

    /**
     * Resolves pending actions needed to bring database to target schema.
     *
     * @return array<string>
     */
    public function resolvePendingActions(): array
    {
        $actions = [];
        $driver = DB::connection()->getDriverName();
        $hasPricePoints = Schema::hasTable('price_points');

        if (!$hasPricePoints) {
            $actions[] = 'create_price_points_table';
        } else {
            $hasItemKey = Schema::hasColumn('price_points', 'item_key');
            if (!$hasItemKey) {
                $actions[] = 'add_item_key_column';
                $actions[] = 'backfill_item_keys';
            } else {
                $hasNullKeys = DB::table('price_points')
                    ->where(function ($q) {
                        $q->whereNull('item_key')->orWhere('item_key', '');
                    })
                    ->exists();

                if ($hasNullKeys) {
                    $actions[] = 'backfill_item_keys';
                }
            }

            if ($this->hasDuplicatePricePoints()) {
                $actions[] = 'deduplicate_price_points';
            }

            if ($driver === 'mysql' && $hasItemKey && $this->isColumnNullable('price_points', 'item_key')) {
                $actions[] = 'make_item_key_not_null';
            }

            if ($this->hasForeignKey('price_points', 'price_points_market_item_id_foreign')) {
                $actions[] = 'drop_legacy_foreign_key';
            }

            if ($this->hasIndex('price_points', 'price_points_market_item_id_fetched_at_unique')) {
                $actions[] = 'drop_legacy_unique_key';
            }

            if ($this->hasIndex('price_points', 'price_points_market_item_id_fetched_at_index')) {
                $actions[] = 'drop_legacy_index';
            }

            if (Schema::hasColumn('price_points', 'raw_payload') && !$this->isColumnNullable('price_points', 'raw_payload')) {
                $actions[] = 'soften_raw_payload';
            }

            if (!$this->hasIndex('price_points', 'price_points_item_key_fetched_at_unique')) {
                $actions[] = 'create_price_points_unique_index';
            }

            if (!$this->hasIndex('price_points', 'price_points_fetched_at_index')) {
                $actions[] = 'create_price_points_fetched_at_index';
            }

            if (!$this->hasIndex('price_points', 'price_points_item_key_current_fetched_at_index')) {
                $actions[] = 'create_price_points_covering_index';
            }
        }

        $hasHourly = Schema::hasTable('price_points_hourly');

        if (!$hasHourly) {
            $actions[] = 'create_price_points_hourly_table';
            $actions[] = 'backfill_hourly_rollups';
        } else {
            if (!$this->hasIndex('price_points_hourly', 'price_points_hourly_item_bucket_unique')) {
                $actions[] = 'create_hourly_unique_index';
            }

            if (!$this->hasIndex('price_points_hourly', 'price_points_hourly_item_bucket_value_index')) {
                $actions[] = 'create_hourly_covering_index';
            }

            if (DB::table('price_points_hourly')->count() === 0
                && Schema::hasTable('price_points')
                && DB::table('price_points')->where('current_value', '>', 0)->exists()
            ) {
                $actions[] = 'backfill_hourly_rollups';
            }
        }

        return $actions;
    }

    /**
     * Executes migrations safely and idempotently.
     *
     * @return array{status:string,message:string,actions:array<string>}
     */
    public function migrate(bool $dryRun = false): array
    {
        $actions = $this->resolvePendingActions();

        if (empty($actions)) {
            return [
                'status' => 'skipped',
                'message' => 'Schema is already up to date. No actions required.',
                'actions' => [],
            ];
        }

        if ($dryRun) {
            return [
                'status' => 'dry_run',
                'message' => 'Dry-run inspection complete. ' . count($actions) . ' actions pending.',
                'actions' => $actions,
            ];
        }

        $executed = [];

        foreach ($actions as $action) {
            switch ($action) {
                case 'create_price_points_table':
                    $this->createPricePointsTable();
                    $executed[] = $action;
                    break;

                case 'add_item_key_column':
                    $this->addItemKeyColumn();
                    $executed[] = $action;
                    break;

                case 'backfill_item_keys':
                    $count = $this->backfillItemKeys();
                    $executed[] = "backfill_item_keys ({$count} rows updated)";
                    break;

                case 'deduplicate_price_points':
                    $count = $this->deduplicatePricePoints();
                    $executed[] = "deduplicate_price_points ({$count} duplicates pruned)";
                    break;

                case 'make_item_key_not_null':
                    $this->makeItemKeyNotNull();
                    $executed[] = $action;
                    break;

                case 'drop_legacy_foreign_key':
                    $this->dropLegacyForeignKey();
                    $executed[] = $action;
                    break;

                case 'drop_legacy_unique_key':
                    $this->dropLegacyUniqueKey();
                    $executed[] = $action;
                    break;

                case 'drop_legacy_index':
                    $this->dropLegacyIndex();
                    $executed[] = $action;
                    break;

                case 'soften_raw_payload':
                    $this->softenRawPayload();
                    $executed[] = $action;
                    break;

                case 'create_price_points_unique_index':
                    $this->createPricePointsUniqueIndex();
                    $executed[] = $action;
                    break;

                case 'create_price_points_fetched_at_index':
                    $this->createPricePointsFetchedAtIndex();
                    $executed[] = $action;
                    break;

                case 'create_price_points_covering_index':
                    $this->createPricePointsCoveringIndex();
                    $executed[] = $action;
                    break;

                case 'create_price_points_hourly_table':
                    $this->createPricePointsHourlyTable();
                    $executed[] = $action;
                    break;

                case 'create_hourly_unique_index':
                    $this->createHourlyUniqueIndex();
                    $executed[] = $action;
                    break;

                case 'create_hourly_covering_index':
                    $this->createHourlyCoveringIndex();
                    $executed[] = $action;
                    break;

                case 'backfill_hourly_rollups':
                    $count = $this->backfillHourlyRollups();
                    $executed[] = "backfill_hourly_rollups ({$count} items rolled up)";
                    break;
            }
        }

        return [
            'status' => 'success',
            'message' => 'Database successfully migrated (' . count($executed) . ' actions executed).',
            'actions' => $executed,
        ];
    }

    private function createPricePointsTable(): void
    {
        Schema::create('price_points', function ($table) {
            $table->id();
            $table->string('item_key');
            $table->decimal('current_value', 18, 4)->nullable();
            $table->decimal('high_value', 18, 4)->nullable();
            $table->decimal('low_value', 18, 4)->nullable();
            $table->decimal('yesterday_avg_value', 18, 4)->nullable();
            $table->decimal('change_value', 18, 4)->nullable();
            $table->decimal('change_percent', 10, 4)->nullable();
            $table->string('direction')->default('none');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['item_key', 'fetched_at'], 'price_points_item_key_fetched_at_unique');
            $table->index('fetched_at', 'price_points_fetched_at_index');
            $table->index(['item_key', 'current_value', 'fetched_at'], 'price_points_item_key_current_fetched_at_index');
        });
    }

    private function createPricePointsHourlyTable(): void
    {
        Schema::create('price_points_hourly', function ($table) {
            $table->id();
            $table->string('item_key');
            $table->timestamp('bucket_at');
            $table->decimal('current_value', 18, 4);
            $table->timestamps();

            $table->unique(['item_key', 'bucket_at'], 'price_points_hourly_item_bucket_unique');
            $table->index(['item_key', 'bucket_at', 'current_value'], 'price_points_hourly_item_bucket_value_index');
        });
    }

    private function addItemKeyColumn(): void
    {
        Schema::table('price_points', function ($table) {
            $table->string('item_key')->nullable()->after('id');
        });
    }

    private function backfillItemKeys(): int
    {
        $updatedCount = 0;
        $catalog = app(MarketCatalog::class);

        $nameMap = [];

        if (Schema::hasTable('market_items')) {
            $items = DB::table('market_items')->get(['id', 'name', 'normalized_name']);
            foreach ($items as $item) {
                $name = $item->name ?: $item->normalized_name;
                if ($name) {
                    $nameMap[(int)$item->id] = PersianNumber::label($name);
                }
            }
        }

        foreach ($catalog->definitions() as $def) {
            $id = (int)$def['id'];
            if (!isset($nameMap[$id])) {
                $nameMap[$id] = $def['key'];
            }
        }

        if (Schema::hasColumn('price_points', 'market_item_id')) {
            foreach ($nameMap as $itemId => $itemKey) {
                $affected = DB::table('price_points')
                    ->where('market_item_id', $itemId)
                    ->where(function ($q) {
                        $q->whereNull('item_key')->orWhere('item_key', '');
                    })
                    ->update(['item_key' => $itemKey]);

                $updatedCount += $affected;
            }
        }

        // Catch any remaining orphaned records with empty item_key
        $orphans = DB::table('price_points')
            ->where(function ($q) {
                $q->whereNull('item_key')->orWhere('item_key', '');
            })
            ->get(['id', 'market_item_id']);

        foreach ($orphans as $orphan) {
            $fallback = 'item_' . ($orphan->market_item_id ?? $orphan->id);
            DB::table('price_points')
                ->where('id', $orphan->id)
                ->update(['item_key' => $fallback]);

            $updatedCount++;
        }

        return $updatedCount;
    }

    private function hasDuplicatePricePoints(): bool
    {
        if (!Schema::hasTable('price_points') || !Schema::hasColumn('price_points', 'item_key')) {
            return false;
        }

        $dup = DB::table('price_points')
            ->select('item_key', 'fetched_at', DB::raw('COUNT(*) as c'))
            ->whereNotNull('item_key')
            ->where('item_key', '!=', '')
            ->groupBy('item_key', 'fetched_at')
            ->having('c', '>', 1)
            ->limit(1)
            ->first();

        return $dup !== null;
    }

    private function deduplicatePricePoints(): int
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            return DB::delete("
                DELETE p1 FROM price_points p1
                INNER JOIN price_points p2
                WHERE p1.item_key = p2.item_key
                  AND p1.fetched_at = p2.fetched_at
                  AND p1.id < p2.id
            ");
        }

        return DB::delete("
            DELETE FROM price_points
            WHERE id NOT IN (
                SELECT MAX(id)
                FROM price_points
                GROUP BY item_key, fetched_at
            )
        ");
    }

    private function makeItemKeyNotNull(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `price_points` MODIFY `item_key` VARCHAR(255) NOT NULL");
        }
    }

    private function dropLegacyForeignKey(): void
    {
        if ($this->hasForeignKey('price_points', 'price_points_market_item_id_foreign')) {
            DB::statement("ALTER TABLE `price_points` DROP FOREIGN KEY `price_points_market_item_id_foreign`");
        }
    }

    private function dropLegacyUniqueKey(): void
    {
        if ($this->hasIndex('price_points', 'price_points_market_item_id_fetched_at_unique')) {
            DB::statement("ALTER TABLE `price_points` DROP INDEX `price_points_market_item_id_fetched_at_unique`");
        }
    }

    private function dropLegacyIndex(): void
    {
        if ($this->hasIndex('price_points', 'price_points_market_item_id_fetched_at_index')) {
            DB::statement("ALTER TABLE `price_points` DROP INDEX `price_points_market_item_id_fetched_at_index`");
        }
    }

    private function softenRawPayload(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' && Schema::hasColumn('price_points', 'raw_payload')) {
            DB::statement("ALTER TABLE `price_points` MODIFY `raw_payload` JSON NULL DEFAULT NULL");
        }
    }

    private function createPricePointsUniqueIndex(): void
    {
        Schema::table('price_points', function ($table) {
            $table->unique(['item_key', 'fetched_at'], 'price_points_item_key_fetched_at_unique');
        });
    }

    private function createPricePointsFetchedAtIndex(): void
    {
        Schema::table('price_points', function ($table) {
            $table->index('fetched_at', 'price_points_fetched_at_index');
        });
    }

    private function createPricePointsCoveringIndex(): void
    {
        Schema::table('price_points', function ($table) {
            $table->index(['item_key', 'current_value', 'fetched_at'], 'price_points_item_key_current_fetched_at_index');
        });
    }

    private function createHourlyUniqueIndex(): void
    {
        Schema::table('price_points_hourly', function ($table) {
            $table->unique(['item_key', 'bucket_at'], 'price_points_hourly_item_bucket_unique');
        });
    }

    private function createHourlyCoveringIndex(): void
    {
        Schema::table('price_points_hourly', function ($table) {
            $table->index(['item_key', 'bucket_at', 'current_value'], 'price_points_hourly_item_bucket_value_index');
        });
    }

    private function backfillHourlyRollups(): int
    {
        $catalog = app(MarketCatalog::class);
        $driver = DB::connection()->getDriverName();

        $keys = $catalog->keys();
        if (empty($keys)) {
            $keys = DB::table('price_points')->distinct()->pluck('item_key')->all();
        }

        $count = 0;
        foreach ($keys as $itemKey) {
            if ($driver === 'mysql') {
                DB::statement(
                    'INSERT INTO price_points_hourly (item_key, bucket_at, current_value, created_at, updated_at)
                     SELECT item_key, bucket_at, current_value, NOW(), NOW()
                     FROM (
                         SELECT item_key,
                                FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(fetched_at) / 3600) * 3600) AS bucket_at,
                                current_value,
                                ROW_NUMBER() OVER (
                                    PARTITION BY FLOOR(UNIX_TIMESTAMP(fetched_at) / 3600)
                                    ORDER BY fetched_at DESC
                                ) AS rn
                         FROM price_points
                         WHERE item_key = ? AND current_value > 0
                     ) ranked
                     WHERE rn = 1
                     ON DUPLICATE KEY UPDATE
                        current_value = VALUES(current_value),
                        updated_at = VALUES(updated_at)',
                    [$itemKey]
                );
                $count++;
            } else {
                $rows = DB::select("
                    SELECT item_key,
                           strftime('%Y-%m-%d %H:00:00', fetched_at) AS bucket_at,
                           current_value
                    FROM (
                        SELECT item_key, fetched_at, current_value,
                               ROW_NUMBER() OVER (
                                   PARTITION BY strftime('%Y-%m-%d %H:00:00', fetched_at)
                                   ORDER BY fetched_at DESC
                               ) as rn
                        FROM price_points
                        WHERE item_key = ? AND current_value > 0
                    )
                    WHERE rn = 1
                ", [$itemKey]);

                foreach ($rows as $r) {
                    DB::table('price_points_hourly')->updateOrInsert(
                        ['item_key' => $r->item_key, 'bucket_at' => $r->bucket_at],
                        ['current_value' => $r->current_value, 'created_at' => now(), 'updated_at' => now()]
                    );
                }
                $count++;
            }
        }

        return $count;
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $idx) {
                if (($idx->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'mysql') {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

            return count($indexes) > 0;
        }

        return false;
    }

    private function hasForeignKey(string $table, string $fkName): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $result = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND CONSTRAINT_NAME = ?
                  AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ", [$table, $fkName]);

            return count($result) > 0;
        }

        return false;
    }

    private function isColumnNullable(string $table, string $column): bool
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return true;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $col = DB::selectOne("
                SELECT IS_NULLABLE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ", [$table, $column]);

            return $col && $col->IS_NULLABLE === 'YES';
        }

        if ($driver === 'sqlite') {
            $cols = DB::select("PRAGMA table_info('{$table}')");
            foreach ($cols as $c) {
                if ($c->name === $column) {
                    return (int)$c->notnull === 0;
                }
            }
        }

        return true;
    }

    private function markAsMigrated(): void
    {
        $statePath = storage_path('framework/' . self::STATE_FILE);
        @file_put_contents($statePath, (string)self::SCHEMA_VERSION);

        Cache::put('gold:db-migrated-version', self::SCHEMA_VERSION, now()->addYears(5));
        Cache::increment('gold:price-data-version');
        Cache::forget('gold:market-summary:data:v2');
        Cache::forget('gold:market-summary:data');
        Cache::forget('gold:market-summary');
    }
}
