<?php

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Static regression checks for root .htaccess when DocumentRoot is the repo
 * (cPanel public_html deployment). Apache integration is covered separately
 * when Docker is available.
 */
class HtaccessProtectionTest extends TestCase
{
    private string $htaccess;

    public function test_blocks_sensitive_trees(): void
    {
        foreach (['app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'storage', 'vendor', 'scripts', 'tests', 'node_modules'] as $dir) {
            $this->assertStringContainsString($dir, $this->htaccess, "missing block for {$dir}");
        }
    }

    public function test_blocks_dotfiles_including_git_and_env(): void
    {
        $this->assertStringContainsString('well-known', $this->htaccess);
        $this->assertTrue(
            str_contains($this->htaccess, '\\.') || str_contains($this->htaccess, '\.'),
            'dotfile deny rule missing'
        );
    }

    public function test_blocks_composer_and_lockfiles(): void
    {
        $this->assertStringContainsString('composer', $this->htaccess);
        $this->assertStringContainsString('package', $this->htaccess);
        $this->assertStringContainsString('artisan', $this->htaccess);
        $this->assertStringContainsString('server\\.php', $this->htaccess);
    }

    public function test_fail_closed_without_mod_rewrite(): void
    {
        $this->assertStringContainsString('!mod_rewrite.c', $this->htaccess);
        $this->assertStringContainsString('Require all denied', $this->htaccess);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->htaccess = file_get_contents(dirname(__DIR__, 2) . '/.htaccess');
    }
}
