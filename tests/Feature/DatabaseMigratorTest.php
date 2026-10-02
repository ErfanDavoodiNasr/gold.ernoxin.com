<?php

namespace Tests\Feature;

use App\Services\DatabaseMigrator;
use App\Services\PersianNumber;
use App\Services\SharedHostingBootstrap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseMigratorTest extends TestCase
{
    private string $stateFile;
    private string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->stateFile = storage_path('framework/database-migrated.ts');
        $this->lockFile = storage_path('framework/database-migration.lock');

        @unlink($this->stateFile);
        @unlink($this->lockFile);

        Schema::dropIfExists('price_points_hourly');
        Schema::dropIfExists('price_points');
        Schema::dropIfExists('market_items');
    }

    protected function tearDown(): void
    {
        @unlink($this->stateFile);
        @unlink($this->lockFile);

        parent::tearDown();
    }

    public function test_fresh_database_creates_all_tables_and_indexes(): void
    {
        $this->assertFalse(Schema::hasTable('price_points'));
        $this->assertFalse(Schema::hasTable('price_points_hourly'));

        $migrator = app(DatabaseMigrator::class);
        $result = $migrator->ensureMigrated();

        $this->assertSame('success', $result['status']);
        $this->assertTrue(Schema::hasTable('price_points'));
        $this->assertTrue(Schema::hasTable('price_points_hourly'));

        $this->assertTrue(Schema::hasColumn('price_points', 'item_key'));
        $this->assertTrue(Schema::hasColumn('price_points', 'current_value'));
        $this->assertTrue(Schema::hasColumn('price_points', 'fetched_at'));

        $this->assertTrue(Schema::hasColumn('price_points_hourly', 'item_key'));
        $this->assertTrue(Schema::hasColumn('price_points_hourly', 'bucket_at'));
        $this->assertTrue(Schema::hasColumn('price_points_hourly', 'current_value'));

        $this->assertTrue($migrator->isMigrated());
        $this->assertFileExists($this->stateFile);
        $this->assertSame((string)DatabaseMigrator::SCHEMA_VERSION, trim(file_get_contents($this->stateFile)));
    }

    public function test_legacy_database_is_safely_migrated_with_zero_data_loss(): void
    {
        // 1. Setup legacy market_items table
        Schema::create('market_items', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->nullable();
        });

        DB::table('market_items')->insert([
            ['id' => 1, 'name' => 'انس طلا', 'normalized_name' => 'انس طلا'],
            ['id' => 3, 'name' => 'طلای ۱۸ عیار', 'normalized_name' => 'طلای ۱۸ عیار'],
            ['id' => 6, 'name' => 'سکه طرح جدید', 'normalized_name' => 'سکه طرح جدید'],
        ]);

        // 2. Setup legacy price_points table (market_item_id instead of item_key)
        Schema::create('price_points', function ($table) {
            $table->id();
            $table->unsignedBigInteger('market_item_id');
            $table->decimal('current_value', 18, 4)->nullable();
            $table->decimal('high_value', 18, 4)->nullable();
            $table->decimal('low_value', 18, 4)->nullable();
            $table->decimal('yesterday_avg_value', 18, 4)->nullable();
            $table->decimal('change_value', 18, 4)->nullable();
            $table->decimal('change_percent', 10, 4)->nullable();
            $table->string('direction')->default('none');
            $table->text('raw_payload')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
        });

        // Seed legacy records
        DB::table('price_points')->insert([
            [
                'id' => 101,
                'market_item_id' => 3,
                'current_value' => 25_000_000,
                'high_value' => 25_100_000,
                'low_value' => 24_900_000,
                'fetched_at' => '2026-10-01 10:15:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 102,
                'market_item_id' => 3,
                'current_value' => 25_050_000,
                'high_value' => 25_100_000,
                'low_value' => 24_900_000,
                'fetched_at' => '2026-10-01 10:45:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 103,
                'market_item_id' => 6,
                'current_value' => 260_000_000,
                'high_value' => 261_000_000,
                'low_value' => 259_000_000,
                'fetched_at' => '2026-10-01 10:20:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migrator = app(DatabaseMigrator::class);
        $result = $migrator->ensureMigrated();

        $this->assertSame('success', $result['status']);
        $this->assertTrue(Schema::hasColumn('price_points', 'item_key'));
        $this->assertTrue(Schema::hasTable('price_points_hourly'));

        // Verify zero data loss: all 3 rows exist
        $this->assertSame(3, DB::table('price_points')->count());

        $row101 = DB::table('price_points')->where('id', 101)->first();
        $this->assertSame(PersianNumber::label('طلای ۱۸ عیار'), $row101->item_key);
        $this->assertEqualsWithDelta(25_000_000.0, (float)$row101->current_value, 0.01);

        $row103 = DB::table('price_points')->where('id', 103)->first();
        $this->assertSame(PersianNumber::label('سکه طرح جدید'), $row103->item_key);
        $this->assertEqualsWithDelta(260_000_000.0, (float)$row103->current_value, 0.01);

        // Verify hourly rollups were populated
        $this->assertGreaterThan(0, DB::table('price_points_hourly')->count());
        $hourlyGold = DB::table('price_points_hourly')
            ->where('item_key', PersianNumber::label('طلای ۱۸ عیار'))
            ->first();
        $this->assertNotNull($hourlyGold);
        // The last value in that hour was 25_050_000
        $this->assertEqualsWithDelta(25_050_000.0, (float)$hourlyGold->current_value, 0.01);
    }

    public function test_deduplicates_conflicting_points_before_unique_index(): void
    {
        Schema::create('price_points', function ($table) {
            $table->id();
            $table->string('item_key');
            $table->decimal('current_value', 18, 4)->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
        });

        // Insert duplicate (item_key, fetched_at)
        DB::table('price_points')->insert([
            ['id' => 1, 'item_key' => 'طلا', 'current_value' => 100, 'fetched_at' => '2026-10-01 12:00:00'],
            ['id' => 2, 'item_key' => 'طلا', 'current_value' => 105, 'fetched_at' => '2026-10-01 12:00:00'],
        ]);

        $migrator = app(DatabaseMigrator::class);
        $result = $migrator->ensureMigrated();

        $this->assertSame('success', $result['status']);
        // Only 1 row remains (max id = 2)
        $this->assertSame(1, DB::table('price_points')->count());
        $remaining = DB::table('price_points')->first();
        $this->assertSame(2, $remaining->id);
        $this->assertEqualsWithDelta(105.0, (float)$remaining->current_value, 0.01);
    }

    public function test_migration_is_idempotent_and_skips_when_already_completed(): void
    {
        $migrator = app(DatabaseMigrator::class);

        $firstRun = $migrator->ensureMigrated();
        $this->assertSame('success', $firstRun['status']);

        // Second run should skip fast
        $secondRun = $migrator->ensureMigrated();
        $this->assertSame('skipped', $secondRun['status']);

        // Forced run should also detect no pending actions
        $forcedRun = $migrator->ensureMigrated(true);
        $this->assertSame('skipped', $forcedRun['status']);
    }

    public function test_shared_hosting_bootstrap_runs_migration_automatically(): void
    {
        $this->assertFalse(Schema::hasTable('price_points'));

        $bootstrap = app(SharedHostingBootstrap::class);
        $bootstrap->run();

        $this->assertTrue(Schema::hasTable('price_points'));
        $this->assertTrue(Schema::hasTable('price_points_hourly'));
        $this->assertFileExists($this->stateFile);
    }

    public function test_artisan_commands_status_dry_run_and_execution(): void
    {
        // 1. Status command on empty database
        $this->artisan('gold:migrate', ['--status' => true])
            ->assertExitCode(0);

        // 2. Dry-run command
        $this->artisan('gold:migrate', ['--dry-run' => true])
            ->assertExitCode(0);

        // 3. Execution command
        $this->artisan('gold:migrate')
            ->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('price_points'));

        // 4. Subsequent run reports already migrated
        $this->artisan('gold:migrate')
            ->assertExitCode(0);
    }
}
