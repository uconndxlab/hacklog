<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TaskAttachment extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'filename',
        'original_name',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the full file path in storage.
     */
    public function getStoragePath(): string
    {
        return "task_attachments/{$this->task_id}/{$this->filename}";
    }

    /**
     * Get the public URL for this attachment.
     */
    public function getUrl(): string
    {
        return Storage::disk('public')->url($this->getStoragePath());
    }

    /**
     * Delete the file from storage.
     */
    public function deleteFile(): void
    {
        Storage::disk('public')->delete($this->getStoragePath());
    }
}
