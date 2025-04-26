<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Projects\DTO;

use Butschster\ContextGenerator\McpServer\Projects\DTO\CurrentProjectDTO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CurrentProjectDTOTest extends TestCase
{
    #[Test]
    public function it_can_be_created_with_path_only(): void
    {
        $dto = new CurrentProjectDTO(
            path: '/path/to/project',
        );

        $this->assertSame('/path/to/project', $dto->path);
        $this->assertFalse($dto->hasConfigFile());
        $this->assertFalse($dto->hasEnvFile());
        $this->assertNull($dto->getConfigFile());
        $this->assertNull($dto->getEnvFile());
    }

    #[Test]
    public function it_can_be_created_with_all_properties(): void
    {
        $dto = new CurrentProjectDTO(
            path: '/path/to/project',
            configFile: 'config.yaml',
            envFile: '.env.test',
        );

        $this->assertSame('/path/to/project', $dto->path);
        $this->assertTrue($dto->hasConfigFile());
        $this->assertTrue($dto->hasEnvFile());

        // Config and env file paths should be joined with project path
        $this->assertSame('/path/to/project/config.yaml', $dto->getConfigFile());
        $this->assertSame('/path/to/project/.env.test', $dto->getEnvFile());
    }

    #[Test]
    public function it_can_be_created_from_array(): void
    {
        $data = [
            'path' => '/path/to/project',
            'config_file' => 'config.yaml',
            'env_file' => '.env.test',
        ];

        $dto = CurrentProjectDTO::fromArray($data);

        $this->assertNotNull($dto);
        $this->assertSame('/path/to/project', $dto->path);
        $this->assertTrue($dto->hasConfigFile());
        $this->assertTrue($dto->hasEnvFile());
    }

    #[Test]
    public function it_returns_null_from_null_or_empty_array(): void
    {
        $this->assertNull(CurrentProjectDTO::fromArray(null));
        $this->assertNull(CurrentProjectDTO::fromArray([]));
        $this->assertNull(CurrentProjectDTO::fromArray(['config_file' => 'config.yaml'])); // Missing path
    }

    #[Test]
    public function it_can_be_serialized_to_json(): void
    {
        $dto = new CurrentProjectDTO(
            path: '/path/to/project',
            configFile: 'config.yaml',
            envFile: '.env.test',
        );

        $json = \json_encode($dto);
        $data = \json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertSame('/path/to/project', $data['path']);
        $this->assertSame('config.yaml', $data['config_file']);
        $this->assertSame('.env.test', $data['env_file']);
    }
}
