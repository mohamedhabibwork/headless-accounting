<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\DocumentAttachment;
use Headless\Accounting\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentVersionFactory extends Factory
{
    protected $model = DocumentVersion::class;

    public function definition(): array
    {
        return [
            'attachment_id' => DocumentAttachment::factory(),
            'version' => 1,
            'storage_path' => 'attachments/'.$this->faker->uuid(),
            'checksum_sha256' => hash('sha256', $this->faker->uuid()),
            'size_bytes' => $this->faker->numberBetween(1000, 10000000),
            'uploader_type' => null,
            'uploader_id' => null,
            'comment' => $this->faker->optional(0.5)->sentence(),
        ];
    }

    public function forAttachment(int $attachmentId): static
    {
        return $this->state(['attachment_id' => $attachmentId]);
    }

    public function version(int $version): static
    {
        return $this->state(['version' => $version]);
    }

    public function uploadedBy(string $type, int $id): static
    {
        return $this->state([
            'uploader_type' => $type,
            'uploader_id' => $id,
        ]);
    }

    public function comment(string $comment): static
    {
        return $this->state(['comment' => $comment]);
    }

    public function size(int $bytes): static
    {
        return $this->state(['size_bytes' => $bytes]);
    }
}
