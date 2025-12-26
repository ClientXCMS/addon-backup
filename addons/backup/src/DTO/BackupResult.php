<?php

namespace App\Addons\Backup\DTO;

use Carbon\Carbon;

class BackupResult
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $type,
        public readonly bool $includesDatabase,
        public readonly bool $includesStorage,
        public readonly Carbon $createdAt,
        public readonly array $metadata = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'type' => $this->type,
            'includes_database' => $this->includesDatabase,
            'includes_storage' => $this->includesStorage,
            'created_at' => $this->createdAt->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }
}
