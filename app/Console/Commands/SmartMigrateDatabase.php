<?php

namespace App\Console\Commands;

use App\Services\DatabaseMigrator;
use Illuminate\Console\Command;

class SmartMigrateDatabase extends Command
{
    protected $signature = 'gold:migrate
                            {--status : نمایش گزارش عیب‌یابی و وضعیت ساختار دیتابیس}
                            {--dry-run : شبیه‌سازی مراحل ارتقا بدون اعمال تغییرات واقعی}
                            {--force : اجرای مهاجرت حتی اگر قبلاً ثبت شده باشد}';

    protected $description = 'مهاجرت و ارتقای هوشمند ساختار دیتابیس با حفظ ۱۰۰٪ داده‌ها';

    public function handle(DatabaseMigrator $migrator): int
    {
        if ($this->option('status')) {
            return $this->showStatus($migrator);
        }

        if ($this->option('dry-run')) {
            return $this->runDryRun($migrator);
        }

        $force = (bool)$this->option('force');

        $this->info('در حال بررسی و اجرای مهاجرت هوشمند پایگاه داده...');

        $result = $migrator->ensureMigrated($force);

        if ($result['status'] === 'skipped') {
            $this->line('<fg=cyan>✔ ' . $result['message'] . '</>');

            return self::SUCCESS;
        }

        if ($result['status'] === 'failed') {
            $this->error('✖ خطا در اجرای مهاجرت: ' . ($result['error'] ?? $result['message']));

            return self::FAILURE;
        }

        if ($result['status'] === 'busy') {
            $this->warn('⚠ ' . $result['message']);

            return self::FAILURE;
        }

        $this->info('<fg=green>✔ ' . $result['message'] . '</>');

        if (!empty($result['actions'])) {
            $this->line('اقدامات انجام‌شده:');
            foreach ($result['actions'] as $action) {
                $this->line("  - <fg=yellow>{$action}</>");
            }
        }

        return self::SUCCESS;
    }

    private function showStatus(DatabaseMigrator $migrator): int
    {
        $this->info('=== گزارش وضعیت پایگاه داده (Database Diagnostics) ===');

        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->error('✖ امکان اتصال به دیتابیس وجود ندارد: ' . $e->getMessage());
            $this->line('لطفاً مطمئن شوید سرویس دیتابیس در حال اجرا است و اطلاعات اتصال در فایل .env صحیح می‌باشد.');

            return self::FAILURE;
        }

        $diag = $migrator->diagnose();

        $this->line('درایور دیتابیس: <fg=yellow>' . $diag['driver'] . '</>');
        $this->line('نسخه تارگت اسکیما: <fg=yellow>v' . $diag['target_schema_version'] . '</>');
        $this->line('وضعیت نشانگر مایگریشن: ' . ($diag['is_migrated_marker'] ? '<fg=green>تکمیل شده</>' : '<fg=yellow>نیاز به بررسی / اجرا</>'));

        $this->newLine();
        $this->line('<fg=cyan>جداول:</>');
        foreach ($diag['tables'] as $tbl => $exists) {
            $this->line("  - {$tbl}: " . ($exists ? '<fg=green>موجود</>' : '<fg=red>ناموجود</>'));
        }

        $this->newLine();
        $this->line('<fg=cyan>تعداد رکوردها:</>');
        $this->line("  - price_points: <fg=yellow>{$diag['counts']['price_points']}</>");
        $this->line("  - price_points_hourly: <fg=yellow>{$diag['counts']['price_points_hourly']}</>");

        if ($diag['legacy']['has_market_item_id']) {
            $this->line('  - ستون قدیمی market_item_id: <fg=magenta>شناسایی شد</>');
        }
        if ($diag['legacy']['null_item_keys_count'] > 0) {
            $this->line("  - تعداد رکوردهای بدون item_key: <fg=red>{$diag['legacy']['null_item_keys_count']}</>");
        }

        $this->newLine();
        if (empty($diag['pending_actions'])) {
            $this->info('✔ ساختار دیتابیس کاملاً به‌روز است. نیازی به هیچ عملیاتی نیست.');
        } else {
            $this->warn('اقدامات در انتظار اجرا (' . count($diag['pending_actions']) . ' مورد):');
            foreach ($diag['pending_actions'] as $act) {
                $this->line("  - <fg=yellow>{$act}</>");
            }
        }

        return self::SUCCESS;
    }

    private function runDryRun(DatabaseMigrator $migrator): int
    {
        $this->warn('حالت Dry-Run: هیچ تغییری در پایگاه داده ثبت نخواهد شد.');

        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->error('✖ امکان اتصال به دیتابیس وجود ندارد: ' . $e->getMessage());
            $this->line('لطفاً مطمئن شوید سرویس دیتابیس در حال اجرا است و اطلاعات اتصال در فایل .env صحیح می‌باشد.');

            return self::FAILURE;
        }

        $result = $migrator->migrate(true);

        $this->line($result['message']);

        if (!empty($result['actions'])) {
            $this->line('اقداماتی که باید اجرا شوند:');
            foreach ($result['actions'] as $action) {
                $this->line("  - <fg=yellow>{$action}</>");
            }
        } else {
            $this->info('✔ هیچ عملیاتی مورد نیاز نیست.');
        }

        return self::SUCCESS;
    }
}
