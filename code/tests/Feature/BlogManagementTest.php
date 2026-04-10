<?php

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can create a published blog post with sanitized html and uploaded cover', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/dashboard/blog/posts', [
        'title' => 'Weekly NEPSE market wrap',
        'slug' => 'weekly-nepse-market-wrap',
        'excerpt' => 'What moved this week and why it mattered.',
        'content_format' => 'markdown',
        'body_source' => "## Market snapshot\n\n<script>alert('xss')</script>\n\nNEPSE closed stronger this week.",
        'tags' => ['nepse', 'weekly-wrap'],
        'source_urls' => ['https://example.com/source'],
        'cover_image' => UploadedFile::fake()->image('cover.png'),
        'cover_image_url' => 'https://example.com/fallback-cover.jpg',
        'status' => 'published',
    ]);

    $post = BlogPost::query()->firstOrFail();

    $response->assertRedirect(route('dashboard.blog.posts.edit', $post, absolute: false));

    expect($post->user_id)->toBe($user->id);
    expect($post->status)->toBe(BlogPostStatus::Published);
    expect($post->published_at)->not->toBeNull();
    expect($post->body_html)->toContain('<h2>Market snapshot</h2>');
    expect($post->body_html)->not->toContain('<script');
    expect($post->cover_image_path)->not->toBeNull();
    expect($post->tags()->pluck('name')->all())->toBe(['nepse', 'weekly-wrap']);
    expect(BlogTag::query()->count())->toBe(2);
    Storage::disk('public')->assertExists($post->cover_image_path);
});

test('uploaded covers take precedence over external urls on the edit screen', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $post = BlogPost::factory()->for($user, 'author')->published()->create([
        'cover_image_path' => UploadedFile::fake()->image('cover.png')->store('blog/covers', 'public'),
        'cover_image_url' => 'https://example.com/fallback-cover.jpg',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.blog.posts.edit', $post));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('blog/admin/Edit')
        ->where('post.coverImageUrl', Storage::disk('public')->url($post->cover_image_path))
    );
});

test('authenticated users can publish then archive blog posts while preserving the published timestamp', function () {
    $user = User::factory()->create();
    $post = BlogPost::factory()->for($user, 'author')->create([
        'status' => BlogPostStatus::Draft,
        'published_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.blog.posts.publish', $post))
        ->assertRedirect();

    $post->refresh();

    expect($post->status)->toBe(BlogPostStatus::Published);
    expect($post->published_at)->not->toBeNull();

    $publishedAt = $post->published_at?->toIso8601String();

    $this->actingAs($user)
        ->post(route('dashboard.blog.posts.archive', $post))
        ->assertRedirect();

    $post->refresh();

    expect($post->status)->toBe(BlogPostStatus::Archived);
    expect($post->published_at?->toIso8601String())->toBe($publishedAt);
});
