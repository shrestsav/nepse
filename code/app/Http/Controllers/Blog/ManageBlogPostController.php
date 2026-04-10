<?php

namespace App\Http\Controllers\Blog;

use App\Enums\BlogContentFormat;
use App\Enums\BlogPostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\StoreBlogPostRequest;
use App\Http\Requests\Blog\UpdateBlogPostRequest;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\Blog\BlogPostContentRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ManageBlogPostController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:draft,published,archived'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;

        $posts = BlogPost::query()
            ->with(['author:id,name', 'tags:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($blogPostQuery) use ($search): void {
                    $blogPostQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%");
                });
            })
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('updated_at')
            ->get();

        $statusCounts = BlogPost::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render('blog/admin/Index', [
            'posts' => $posts->map(fn (BlogPost $post): array => $this->mapAdminSummary($post))->all(),
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'status' => $status,
            ],
            'statusCounts' => [
                'all' => (int) BlogPost::query()->count(),
                'draft' => (int) ($statusCounts['draft'] ?? 0),
                'published' => (int) ($statusCounts['published'] ?? 0),
                'archived' => (int) ($statusCounts['archived'] ?? 0),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('blog/admin/Create', [
            'post' => $this->emptyPostPayload(),
            'statusOptions' => $this->statusOptions(),
            'formatOptions' => $this->formatOptions(),
        ]);
    }

    public function store(StoreBlogPostRequest $request, BlogPostContentRenderer $renderer): RedirectResponse
    {
        $blogPost = new BlogPost();
        $blogPost->author()->associate($request->user());

        $this->persistBlogPost($blogPost, $request->validated(), $request->file('cover_image'), $renderer);

        return to_route('dashboard.blog.posts.edit', $blogPost)
            ->with('success', 'Blog post created.');
    }

    public function edit(BlogPost $blogPost): Response
    {
        $blogPost->loadMissing(['author:id,name', 'tags:id,name']);

        return Inertia::render('blog/admin/Edit', [
            'post' => $this->mapAdminDetail($blogPost),
            'statusOptions' => $this->statusOptions(),
            'formatOptions' => $this->formatOptions(),
        ]);
    }

    public function update(
        UpdateBlogPostRequest $request,
        BlogPost $blogPost,
        BlogPostContentRenderer $renderer,
    ): RedirectResponse {
        $this->persistBlogPost($blogPost, $request->validated(), $request->file('cover_image'), $renderer);

        return back()->with('success', 'Blog post updated.');
    }

    public function destroy(BlogPost $blogPost): RedirectResponse
    {
        if (filled($blogPost->cover_image_path)) {
            Storage::disk('public')->delete($blogPost->cover_image_path);
        }

        $blogPost->delete();

        return to_route('dashboard.blog.posts.index')
            ->with('success', 'Blog post deleted.');
    }

    public function publish(BlogPost $blogPost, BlogPostContentRenderer $renderer): RedirectResponse
    {
        $blogPost->body_html = $renderer->render($blogPost->content_format, $blogPost->body_source);
        $blogPost->status = BlogPostStatus::Published;
        $blogPost->published_at ??= now();
        $blogPost->save();

        return back()->with('success', 'Blog post published.');
    }

    public function archive(BlogPost $blogPost): RedirectResponse
    {
        $blogPost->status = BlogPostStatus::Archived;
        $blogPost->save();

        return back()->with('success', 'Blog post archived.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function persistBlogPost(
        BlogPost $blogPost,
        array $validated,
        ?UploadedFile $coverImage,
        BlogPostContentRenderer $renderer,
    ): void {
        $validated['content_format'] = $validated['content_format'] instanceof BlogContentFormat
            ? $validated['content_format']
            : BlogContentFormat::from($validated['content_format']);
        $validated['status'] = $validated['status'] instanceof BlogPostStatus
            ? $validated['status']
            : BlogPostStatus::from($validated['status']);

        $blogPost->fill([
            'slug' => $validated['slug'],
            'title' => $validated['title'],
            'excerpt' => $validated['excerpt'] ?? null,
            'content_format' => $validated['content_format'],
            'body_source' => $validated['body_source'],
            'body_html' => $renderer->render($validated['content_format'], $validated['body_source']),
            'source_urls' => $validated['source_urls'] ?? [],
            'cover_image_url' => $validated['cover_image_url'] ?? null,
            'status' => $validated['status'],
        ]);

        if (($validated['remove_cover_image'] ?? false) && filled($blogPost->getOriginal('cover_image_path'))) {
            Storage::disk('public')->delete($blogPost->getOriginal('cover_image_path'));
            $blogPost->cover_image_path = null;
        }

        if ($coverImage instanceof UploadedFile) {
            if (filled($blogPost->getOriginal('cover_image_path'))) {
                Storage::disk('public')->delete($blogPost->getOriginal('cover_image_path'));
            }

            $blogPost->cover_image_path = $coverImage->store('blog/covers', 'public');
        }

        if ($blogPost->status === BlogPostStatus::Published && $blogPost->published_at === null) {
            $blogPost->published_at = now();
        }

        $blogPost->save();
        $this->syncTags($blogPost, $validated['tags'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAdminSummary(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'status' => $post->status?->value,
            'statusLabel' => $post->status?->label(),
            'contentFormat' => $post->content_format?->value,
            'coverImageUrl' => $post->coverImageUrl(),
            'tags' => $post->tagNames(),
            'publishedAt' => $post->published_at?->toIso8601String(),
            'updatedAt' => $post->updated_at?->toIso8601String(),
            'author' => $post->author?->name,
            'publicUrl' => $post->isPublished()
                ? route('blog.show', ['blogPost' => $post->slug])
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAdminDetail(BlogPost $post): array
    {
        return [
            ...$this->mapAdminSummary($post),
            'bodySource' => $post->body_source,
            'bodyHtml' => $post->body_html,
            'coverImagePath' => $post->cover_image_path,
            'sourceUrls' => $post->source_urls ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPostPayload(): array
    {
        return [
            'id' => null,
            'title' => '',
            'slug' => '',
            'excerpt' => '',
            'status' => BlogPostStatus::Draft->value,
            'statusLabel' => BlogPostStatus::Draft->label(),
            'contentFormat' => BlogContentFormat::Markdown->value,
            'bodySource' => '',
            'bodyHtml' => '',
            'coverImageUrl' => null,
            'coverImagePath' => null,
            'tags' => [],
            'sourceUrls' => [],
            'publishedAt' => null,
            'updatedAt' => null,
            'author' => null,
            'publicUrl' => null,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            static fn (BlogPostStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            BlogPostStatus::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function formatOptions(): array
    {
        return array_map(
            static fn (BlogContentFormat $format): array => [
                'value' => $format->value,
                'label' => $format->label(),
            ],
            BlogContentFormat::cases(),
        );
    }

    /**
     * @param  list<string>  $tags
     */
    private function syncTags(BlogPost $blogPost, array $tags): void
    {
        $tagIds = Collection::make($tags)
            ->filter(fn (mixed $tag): bool => is_string($tag) && trim($tag) !== '')
            ->map(function (string $tag): int {
                $normalizedName = Str::of($tag)->trim()->squish()->toString();
                $blogTag = BlogTag::query()->firstOrCreate(
                    ['name' => $normalizedName],
                );

                return $blogTag->id;
            })
            ->unique()
            ->values()
            ->all();

        $blogPost->tags()->sync($tagIds);
    }
}
