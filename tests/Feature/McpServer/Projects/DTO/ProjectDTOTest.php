<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Projects\DTO;

use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectDTO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProjectDTOTest extends TestCase
{
    #[Test]
    public function it_can_be_created_with_path_only(): void
    {
        $dto = new ProjectDTO(
            path: '/path/to/project',
        );

        $this->assertSame('/path/to/project', $dto->path);
        $this->assertEmpty($dto->addedAt); // Default timestamp
        $this->assertNull($dto->configFile);
        $this->assertNull($dto->envFile);
    }

    #[Test]
    public function it_can_be_created_with_all_properties(): void
    {
        $dto = new ProjectDTO(
            path: '/path/to/project',
            addedAt: '2023-01-01 12:00:00',
            configFile: 'config.yaml',
            envFile: '.env.test',
        );

        $this->assertSame('/path/to/project', $dto->path);
        $this->assertSame('2023-01-01 12:00:00', $dto->addedAt);
        $this->assertSame('config.yaml', $dto->configFile);
        $this->assertSame('.env.test', $dto->envFile);
    }

    #[Test]
    public function it_can_be_created_from_array(): void
    {
        $data = [
            'added_at' => '2023-01-01 12:00:00',
            'config_file' => 'config.yaml',
            'env_file' => '.env.test',
        ];

        $dto = ProjectDTO::fromArray('/path/to/project', $data);

        $this->assertSame('/path/to/project', $dto->path);
        $this->assertSame('2023-01-01 12:00:00', $dto->addedAt);
        $this->assertSame('config.yaml', $dto->configFile);
        $this->assertSame('.env.test', $dto->envFile);
    }

    #[Test]
    public function it_uses_current_date_when_added_at_missing(): void
    {
        $data = [
            'config_file' => 'config.yaml',
        ];

        $dto = ProjectDTO::fromArray('/path/to/project', $data);

        $this->assertSame('/path/to/project', $dto->path);
        $this->assertNotEmpty($dto->addedAt); // Should have a default value

        // Verify date format is Y-m-d H:i:s
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dto->addedAt);
    }

    #[Test]
    public function it_can_be_serialized_to_json(): void
    {
        $dto = new ProjectDTO(
            path: '/path/to/project',
            addedAt: '2023-01-01 12:00:00',
            configFile: 'config.yaml',
            envFile: '.env.test',
        );

        $json = \json_encode($dto);
        $data = \json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertSame('2023-01-01 12:00:00', $data['added_at']);
        $this->assertSame('config.yaml', $data['config_file']);
        $this->assertSame('.env.test', $data['env_file']);

        // Path is not serialized as it's used as array key in ProjectStateDTO
        $this->assertArrayNotHasKey('path', $data);
    }
}
