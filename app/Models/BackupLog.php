<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'backup_type',
    'status',
    'filename',
    'path',
    'file_size',
    'checksum',
    'started_at',
    'completed_at',
    'error_message',
    'metadata',
])]
class BackupLog extends Model
{
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'completed'
            && $this->path
            && is_file($this->path);
    }

    public function formattedFileSize(): string
    {
        if (! $this->file_size) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, $unit === 0 ? 0 : 1).' '.$units[$unit];
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'completed' => 'success-pill',
            'failed' => 'danger-pill',
            'running' => 'warning-pill',
            default => 'muted-pill',
        };
    }
}
