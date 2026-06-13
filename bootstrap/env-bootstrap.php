<?php

function env_bootstrap_base_path(): string
{
    return dirname(__DIR__);
}

function env_bootstrap_chmod_file(string $file): void
{
    @chmod($file, 0600);

    if (!is_writable($file)) {
        @chmod($file, 0640);
    }
}

function env_bootstrap_example_path(string $basePath): string
{
    return $basePath . DIRECTORY_SEPARATOR . '.env.example';
}

function env_bootstrap_create_env_from_example(?string $basePath = null): bool
{
    $basePath ??= env_bootstrap_base_path();
    $envPath = $basePath . DIRECTORY_SEPARATOR . '.env';
    $examplePath = env_bootstrap_example_path($basePath);

    if (is_file($envPath)) {
        return true;
    }

    if (!is_file($examplePath)) {
        return false;
    }

    if (@copy($examplePath, $envPath)) {
        env_bootstrap_chmod_file($envPath);

        return true;
    }

    return false;
}

function env_bootstrap_ensure_app_key(?string $basePath = null): ?string
{
    $basePath ??= env_bootstrap_base_path();
    $envPath = $basePath . DIRECTORY_SEPARATOR . '.env';

    if (!is_file($envPath)) {
        return null;
    }

    $contents = @file_get_contents($envPath);
    if ($contents === false) {
        return null;
    }

    if (preg_match('/^APP_KEY=(.*)$/m', $contents, $matches)) {
        $value = trim($matches[1], " \t\"'");

        if ($value !== '') {
            return null;
        }
    }

    try {
        $key = 'base64:' . base64_encode(random_bytes(32));
    } catch (\Throwable) {
        return null;
    }

    if (!env_bootstrap_write_env_value($envPath, 'APP_KEY', $key, $contents)) {
        return null;
    }

    return $key;
}

function env_bootstrap_write_env_value(string $envPath, string $name, string $value, ?string $contents = null): bool
{
    $line = $name . '=' . $value;

    if ($contents === null) {
        if (!is_file($envPath)) {
            return false;
        }

        $contents = @file_get_contents($envPath);
        if ($contents === false) {
            return false;
        }
    }

    if (preg_match('/^' . preg_quote($name, '/') . '=.*$/m', $contents)) {
        $contents = preg_replace('/^' . preg_quote($name, '/') . '=.*$/m', $line, $contents);
    } else {
        $contents = rtrim($contents) . PHP_EOL . $line . PHP_EOL;
    }

    env_bootstrap_chmod_file($envPath);

    return @file_put_contents($envPath, $contents, LOCK_EX) !== false;
}

function env_bootstrap_run(?string $basePath = null): void
{
    $basePath ??= env_bootstrap_base_path();
    env_bootstrap_create_env_from_example($basePath);
    env_bootstrap_ensure_app_key($basePath);
}
