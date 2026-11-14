<?php

declare(strict_types=1);

namespace ConfigCartographer\Tests\Integration\Command;

use ConfigCartographer\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zenstruck\Console\Test\TestCommand;

#[CoversClass(\ConfigCartographer\Command\GraphCommand::class)]
class GraphCommandIntegrationTest extends TestCase
{
    private Application $application;

    protected function setUp(): void
    {
        $this->application = new Application();
    }

    public function test_graph_mermaid_output_is_valid(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/symfony-app';

        TestCommand::from($this->application, "graph --path={$fixturePath} --format=mermaid")
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('flowchart');
    }

    public function test_graph_json_output_is_valid(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/symfony-app';

        $result = TestCommand::from($this->application, "graph --path={$fixturePath} --format=json")
            ->execute()
            ->assertSuccessful();

        $json = json_decode($result->output(), true);
        $this->assertIsArray($json);
    }

    public function test_graph_variable_filter_works(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/symfony-app';

        TestCommand::from($this->application, "graph --path={$fixturePath} --variable=DATABASE_URL")
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('DATABASE_URL');
    }

    public function test_graph_output_is_deterministic(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/symfony-app';
        $command = "graph --path={$fixturePath} --format=json";

        $result1 = TestCommand::from($this->application, $command)->execute()->assertSuccessful();
        $result2 = TestCommand::from($this->application, $command)->execute()->assertSuccessful();

        $this->assertEquals($result1->output(), $result2->output(), 'Graph output should be deterministic');
    }

    public function test_graph_secret_values_not_in_output(): void
    {
        $fixturePath = __DIR__ . '/../../Fixtures/symfony-app';

        TestCommand::from($this->application, "graph --path={$fixturePath} --format=json")
            ->execute()
            ->assertSuccessful()
            ->assertOutputNotContains('canary_symfony_secret');
    }
}
