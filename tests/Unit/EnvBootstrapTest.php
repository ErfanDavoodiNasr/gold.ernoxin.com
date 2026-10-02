<?php

namespace Tests\Unit;

use Tests\TestCase;

class EnvBootstrapTest extends TestCase
{
    public function test_does_not_rotate_existing_app_key(): void
    {
        $dir = sys_get_temp_dir() . '/gold-env-' . uniqid();
        mkdir($dir);
        $existing = 'base64:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa=';
        file_put_contents($dir . '/.env', "APP_KEY={$existing}\nAPP_NAME=test\n");
        file_put_contents($dir . '/.env.example', "APP_KEY=\nAPP_NAME=example\n");

        require_once base_path('bootstrap/env-bootstrap.php');
        $returned = env_bootstrap_ensure_app_key($dir);
        $this->assertNull($returned);
        $this->assertStringContainsString("APP_KEY={$existing}", file_get_contents($dir . '/.env'));

        @unlink($dir . '/.env');
        @unlink($dir . '/.env.example');
        @rmdir($dir);
    }

    public function test_fills_empty_app_key_once(): void
    {
        $dir = sys_get_temp_dir() . '/gold-env-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/.env', "APP_KEY=\nDB_PASSWORD=p@ss#word=ok\n");

        require_once base_path('bootstrap/env-bootstrap.php');
        $key = env_bootstrap_ensure_app_key($dir);
        $this->assertNotNull($key);
        $this->assertStringStartsWith('base64:', $key);
        $contents = file_get_contents($dir . '/.env');
        $this->assertStringContainsString("APP_KEY={$key}", $contents);
        $this->assertStringContainsString('DB_PASSWORD=p@ss#word=ok', $contents);

        $again = env_bootstrap_ensure_app_key($dir);
        $this->assertNull($again);

        @unlink($dir . '/.env');
        @rmdir($dir);
    }

    public function test_create_env_from_example_is_idempotent(): void
    {
        $dir = sys_get_temp_dir() . '/gold-env-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/.env.example', "APP_KEY=\nFOO=bar\n");

        require_once base_path('bootstrap/env-bootstrap.php');
        $this->assertTrue(env_bootstrap_create_env_from_example($dir));
        $this->assertTrue(is_file($dir . '/.env'));
        $before = file_get_contents($dir . '/.env');
        $this->assertTrue(env_bootstrap_create_env_from_example($dir));
        $this->assertSame($before, file_get_contents($dir . '/.env'));

        @unlink($dir . '/.env');
        @unlink($dir . '/.env.example');
        @rmdir($dir);
    }
}
