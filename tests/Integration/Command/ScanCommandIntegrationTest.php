<?php

declare(strict_types=1);

namespace ConfigCartographer\Tests\Integration\Command;

use ConfigCartographer\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenstruck\Console\Test\TestCommand;

#[CoversClass(\ConfigCartographer\Command\ScanCommand::class)]
class ScanCommandIntegrationTest extends TestCase
{
    private Application $application;

    protected function setUp(): void
    {
        $this->application = new Application();
    }

    public function test_scan_minimal_app_shows_counts(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/minimal-app';

        TestCommand::from($this->application, "scan --path={$fixturePath}")
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('Environment variables: 3');
    }

    public function test_scan_json_output_is_valid(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/minimal-app';

        $result = TestCommand::from($this->application, "scan --path={$fixturePath} --format=json")
            ->execute()
            ->assertSuccessful();

        $json = json_decode($result->output(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('version', $json);
    }

    public function test_scan_invalid_path_returns_error(): void
    {
        TestCommand::from($this->application, 'scan --path=/nonexistent/path')
            ->execute()
            ->assertStatusCode(1);
    }

    public function test_scan_secret_values_not_in_output(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/minimal-app';

        TestCommand::from($this->application, "scan --path={$fixturePath} --format=json")
            ->execute()
            ->assertSuccessful()
            ->assertOutputNotContains('canary_secret_do_notExpose');
    }
}
