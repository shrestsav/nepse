<?php

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use Inertia\Testing\AssertableInertia as Assert;

test('home renders the public blog feed for guests', function () {
    $published = BlogPost::factory()->published()->create([
        'title' => 'Market wrap',
        'slug' => 'market-wrap',
    ]);
    BlogPost::factory()->create([
        'title' => 'Draft only',
        'slug' => 'draft-only',
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('blog/Index')
        ->where('isAuthenticated', false)
        ->has('posts', 1)
        ->where('posts.0.slug', $published->slug)
    );
});

test('public blog detail page only resolves published posts', function () {
    $published = BlogPost::factory()->published()->create([
        'slug' => 'published-nepse-update',
    ]);
    BlogPost::factory()->create([
        'slug' => 'draft-nepse-update',
        'status' => BlogPostStatus::Draft,
    ]);
    BlogPost::factory()->create([
        'slug' => 'archived-nepse-update',
        'status' => BlogPostStatus::Archived,
    ]);

    $response = $this->get(route('blog.show', ['blogPost' => $published->slug]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('blog/Show')
        ->where('post.slug', $published->slug)
    );

    $this->get(route('blog.show', ['blogPost' => 'draft-nepse-update']))
        ->assertNotFound();

    $this->get(route('blog.show', ['blogPost' => 'archived-nepse-update']))
        ->assertNotFound();
});
