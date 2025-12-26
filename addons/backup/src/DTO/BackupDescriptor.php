<?php

namespace App\Addons\Backup\DTO;

use Carbon\Carbon;

class BackupDescriptor
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $filename,
        public readonly Carbon $createdAt,
        public readonly string $type,
        public readonly int $size,
        public readonly string $provider,
        public readonly string $disk,
        public readonly array $metadata = []
    ) {}

    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'filename' => $this->filename,
            'created_at' => $this->createdAt->toIso8601String(),
            'type' => $this->type,
            'size' => $this->size,
            'provider' => $this->provider,
            'disk' => $this->disk,
            'metadata' => $this->metadata,
        ];
    }
}
