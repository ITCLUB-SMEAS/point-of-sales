<?php

namespace Tests\Feature;

use Tests\TestCase;

class InfrastructureConfigurationTest extends TestCase
{
    public function test_ci_workflow_runs_backend_and_frontend_checks(): void
    {
        $workflow = $this->projectFile('.github/workflows/ci.yml');

        $this->assertFileExists($workflow);
        $contents = file_get_contents($workflow);

        $this->assertStringContainsString('php artisan test --compact', $contents);
        $this->assertStringContainsString('vendor/bin/pint --test --format=github', $contents);
        $this->assertStringContainsString('bun run build', $contents);
    }

    public function test_frankenphp_docker_image_is_configured_for_laravel(): void
    {
        $dockerfile = $this->projectFile('Dockerfile');
        $caddyfile = $this->projectFile('docker/frankenphp/Caddyfile');
        $entrypoint = $this->projectFile('docker/frankenphp/entrypoint.sh');

        $this->assertFileExists($dockerfile);
        $this->assertFileExists($caddyfile);
        $this->assertFileExists($entrypoint);

        $dockerfileContents = file_get_contents($dockerfile);
        $caddyfileContents = file_get_contents($caddyfile);
        $entrypointContents = file_get_contents($entrypoint);

        $this->assertStringContainsString('dunglas/frankenphp:php8.4', $dockerfileContents);
        $this->assertStringContainsString('composer install', $dockerfileContents);
        $this->assertStringContainsString('bun run build', $dockerfileContents);
        $this->assertStringContainsString('php_server', $caddyfileContents);
        $this->assertStringContainsString('root * /app/public', $caddyfileContents);
        $this->assertStringContainsString('php artisan optimize', $entrypointContents);
        $this->assertStringContainsString('php artisan migrate --force', $entrypointContents);
    }

    public function test_docker_compose_provides_application_database_and_cache_services(): void
    {
        $compose = $this->projectFile('docker-compose.yml');

        $this->assertFileExists($compose);
        $contents = file_get_contents($compose);

        $this->assertStringContainsString('frankenphp', $contents);
        $this->assertStringContainsString('mysql:8.4', $contents);
        $this->assertStringContainsString('redis:7-alpine', $contents);
        $this->assertStringContainsString('APP_ENV=production', $contents);
        $this->assertStringContainsString('DB_HOST=mysql', $contents);
    }

    private function projectFile(string $path): string
    {
        return base_path($path);
    }
}
