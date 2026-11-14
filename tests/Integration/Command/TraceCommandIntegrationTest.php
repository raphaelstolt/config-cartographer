<?php

declare(strict_types=1);

namespace ConfigCartographer\Tests\Integration\Command;

use ConfigCartographer\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenstruck\Console\Test\TestCommand;

#[CoversClass(\ConfigCartographer\Command\TraceCommand::class)]
class TraceCommandIntegrationTest extends TestCase
{
    private Application $application;

    protected function setUp(): void
    {
        $this->application = new Application();
    }

    public function test_trace_database_url_shows_references(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/symfony-app';

        TestCommand::from($this->application, "trace DATABASE_URL --path={$fixturePath}")
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('DATABASE_URL');
    }

    public function test_trace_missing_variable_returns_error(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/minimal-app';

        TestCommand::from($this->application, "trace NONEXISTENT_VAR --path={$fixturePath}")
            ->execute()
            ->assertStatusCode(1);
    }

    public function test_trace_json_output_is_valid(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/symfony-app';

        $result = TestCommand::from($this->application, "trace DATABASE_URL --path={$fixturePath} --format=json")
            ->execute()
            ->assertSuccessful();

        $json = json_decode($result->output(), true);
        $this->assertIsArray($json);
    }

    public function test_trace_secret_values_not_in_output(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/symfony-app';

        TestCommand::from($this->application, "trace APP_SECRET --path={$fixturePath} --format=json")
            ->execute()
            ->assertSuccessful()
            ->assertOutputNotContains('canary_symfony_secret');
    }
}
