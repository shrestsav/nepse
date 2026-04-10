<?php

namespace App\Models;

use App\Enums\BlogContentFormat;
use App\Enums\BlogPostStatus;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'slug',
        'title',
        'excerpt',
        'content_format',
        'body_source',
        'body_html',
        'source_urls',
        'cover_image_path',
        'cover_image_url',
        'status',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_format' => BlogContentFormat::class,
            'status' => BlogPostStatus::class,
            'source_urls' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag');
    }

    /**
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', BlogPostStatus::Published)
            ->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->status === BlogPostStatus::Published
            && $this->published_at !== null;
    }

    public function coverImageUrl(): ?string
    {
        if (filled($this->cover_image_path)) {
            return Storage::disk('public')->url($this->cover_image_path);
        }

        if (filled($this->cover_image_url)) {
            return $this->cover_image_url;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function tagNames(): array
    {
        $this->loadMissing('tags:id,name');

        /** @var list<string> $tagNames */
        $tagNames = $this->tags
            ->pluck('name')
            ->filter(fn (mixed $tag): bool => is_string($tag) && $tag !== '')
            ->values()
            ->all();

        return $tagNames;
    }
}
