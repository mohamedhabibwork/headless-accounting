<?php

declare(strict_types=1);

namespace Headless\Accounting\Database\Factories;

use Headless\Accounting\Models\DocumentAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentAttachmentFactory extends Factory
{
    protected $model = DocumentAttachment::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'filename' => $this->faker->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => $this->faker->numberBetween(1000, 10000000),
            'storage_disk' => 'local',
            'storage_path' => 'attachments/'.$this->faker->uuid().'.pdf',
            'checksum_sha256' => hash('sha256', $this->faker->uuid()),
            'ocr_processed' => false,
            'ocr_result' => null,
            'extra_metadata' => null,
            'requires_signature' => false,
            'signed_at' => null,
            'signed_by' => null,
        ];
    }

    public function forSubject(string $type, int $id): static
    {
        return $this->state([
            'subject_type' => $type,
            'subject_id' => $id,
        ]);
    }

    public function pdf(?string $filename = null): static
    {
        return $this->state([
            'mime_type' => 'application/pdf',
            'filename' => $filename ?? $this->faker->word().'.pdf',
        ]);
    }

    public function image(?string $filename = null): static
    {
        return $this->state([
            'mime_type' => 'image/jpeg',
            'filename' => $filename ?? $this->faker->word().'.jpg',
        ]);
    }

    public function onDisk(string $disk, ?string $path = null): static
    {
        return $this->state([
            'storage_disk' => $disk,
            'storage_path' => $path ?? 'attachments/'.$this->faker->uuid(),
        ]);
    }

    public function ocrProcessed(array $result = ['text' => 'Sample text']): static
    {
        return $this->state([
            'ocr_processed' => true,
            'ocr_result' => $result,
        ]);
    }

    public function requiresSignature(): static
    {
        return $this->state(['requires_signature' => true]);
    }

    public function signed(?int $signedBy = null): static
    {
        return $this->state([
            'requires_signature' => true,
            'signed_at' => now(),
            'signed_by' => $signedBy,
        ]);
    }

    public function size(int $bytes): static
    {
        return $this->state(['size_bytes' => $bytes]);
    }
}
