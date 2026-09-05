<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Media\Type as MediaType;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory, HasUuids;

    protected $table = 'medias';

    protected $appends = ['url'];

    protected $fillable = [
        'mediable_id',
        'mediable_type',
        'workspace_id',
        'folder_id',
        'uploaded_by',
        'group_id',
        'collection',
        'type',
        'path',
        'original_filename',
        'mime_type',
        'size',
        'order',
        'meta',
        'upload_token',
        'disk',
        'original_path',
        'optimized_path',
        'thumbnail_path',
        'checksum',
    ];

    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'size' => 'integer',
            'order' => 'integer',
            'meta' => 'array',
        ];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_media')
            ->withPivot(['sort_order', 'alt_text', 'meta'])
            ->withTimestamps();
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withTimestamps();
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::url($this->path),
        );
    }

    public function isVideo(): bool
    {
        return MediaType::classify($this->mime_type, $this->path) === MediaType::Video;
    }

    public function isImage(): bool
    {
        return MediaType::classify($this->mime_type, $this->path) === MediaType::Image;
    }

    public function isDocument(): bool
    {
        return MediaType::classify($this->mime_type, $this->path) === MediaType::Document;
    }

    public function getTemporaryUrl(int $expirationMinutes = 60): string
    {
        return Storage::temporaryUrl(
            $this->path,
            now()->addMinutes($expirationMinutes)
        );
    }

    public function delete(): bool
    {
        // Only delete the file if no other media records use the same path
        $otherMediaWithSamePath = static::where('path', $this->path)
            ->where('id', '!=', $this->id)
            ->exists();

        if (! $otherMediaWithSamePath) {
            Storage::delete($this->path);
        }

        return parent::delete();
    }
}
