<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SharedHostingBootstrap
{
    public function run(): void
    {
        $this->loadEnvBootstrap();

        $this->ensureWritableDirectories();
        $this->ensureEnvironmentFile();
        $this->ensureApplicationKey();
        $this->ensureDatabaseSchema();
    }

    private function loadEnvBootstrap(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        require_once base_path('bootstrap/env-bootstrap.php');
        $loaded = true;
    }

    private function ensureWritableDirectories(): void
    {
        if (!config('gold.hosting.ensure_writable_paths')) {
            return;
        }

        foreach ($this->writableDirectories() as $directory) {
            $this->ensureWritableDirectory($directory);
        }

        foreach ($this->writableFiles() as $file) {
            $this->ensureWritableFile($file);
        }
    }

    private function writableDirectories(): array
    {
        return [
            storage_path(),
            storage_path('app'),
            storage_path('framework'),
            storage_path('framework/cache'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];
    }

    private function ensureWritableDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        if (!is_dir($directory)) {
            return;
        }

        $this->chmodDirectory($directory);

        $items = @scandir($directory);
        if (!$items) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->ensureWritableDirectory($path);
            } elseif (is_file($path)) {
                $this->chmodFile($path);
            }
        }
    }

    private function chmodDirectory(string $directory): void
    {
        @chmod($directory, 0775);

        if (!is_writable($directory)) {
            @chmod($directory, 0777);
        }
    }

    private function chmodFile(string $file): void
    {
        @chmod($file, 0664);

        if (!is_writable($file)) {
            @chmod($file, 0666);
        }
    }

    private function writableFiles(): array
    {
        return [
            storage_path('logs/laravel.log'),
            storage_path('framework/hosting-bootstrap.lock'),
        ];
    }

    private function ensureWritableFile(string $file): void
    {
        $directory = dirname($file);
        $this->ensureWritableDirectory($directory);

        if (!file_exists($file)) {
            @touch($file);
        }

        if (is_file($file)) {
            $this->chmodFile($file);
        }
    }

    private function ensureEnvironmentFile(): void
    {
        env_bootstrap_promote_example(base_path());
    }

    private function ensureApplicationKey(): void
    {
        if (config('app.key')) {
            return;
        }

        try {
            $key = env_bootstrap_ensure_app_key(base_path());

            if ($key) {
                config(['app.key' => $key]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function ensureDatabaseSchema(): void
    {
        if (!config('gold.hosting.auto_migrate')) {
            return;
        }

        $lockPath = storage_path('framework/hosting-bootstrap.lock');
        $lock = @fopen($lockPath, 'c');

        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return;
        }

        try {
            DB::connection()->getPdo();

            $repository = app('migration.repository');

            if (!$repository->repositoryExists()) {
                $repository->createRepository();
            }

            $migrator = app('migrator');
            $migrationPath = database_path('migrations');
            $migrationFiles = $migrator->getMigrationFiles($migrationPath);
            $pendingMigrations = array_diff(array_keys($migrationFiles), $repository->getRan());

            if ($pendingMigrations) {
                $migrator->run($migrationPath, ['force' => true]);
            }
        } catch (\Throwable $e) {
            report($e);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
