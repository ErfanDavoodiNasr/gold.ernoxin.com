<?php

$envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
$examplePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env.example';

if (!is_file($envPath) && is_file($examplePath)) {
    @rename($examplePath, $envPath);
}
