<?php

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicBlogController extends Controller
{
    public function index(Request $request): Response
    {
        $posts = BlogPost::query()
            ->published()
            ->with(['author:id,name', 'tags:id,name'])
            ->orderByDesc('published_at')
            ->limit(24)
            ->get();

        return Inertia::render('blog/Index', [
            'posts' => $posts->map(fn (BlogPost $post): array => $this->mapPublicPost($post))->all(),
            'isAuthenticated' => $request->user() !== null,
        ]);
    }

    public function show(Request $request, BlogPost $blogPost): Response
    {
        abort_unless($blogPost->isPublished(), 404);

        $related = BlogPost::query()
            ->published()
            ->whereKeyNot($blogPost->getKey())
            ->with(['author:id,name', 'tags:id,name'])
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return Inertia::render('blog/Show', [
            'post' => $this->mapPublicPost($blogPost, includeBody: true),
            'relatedPosts' => $related->map(fn (BlogPost $post): array => $this->mapPublicPost($post))->all(),
            'isAuthenticated' => $request->user() !== null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPublicPost(BlogPost $post, bool $includeBody = false): array
    {
        $data = [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'tags' => $post->tagNames(),
            'sourceUrls' => $post->source_urls ?? [],
            'coverImageUrl' => $post->coverImageUrl(),
            'publishedAt' => $post->published_at?->toIso8601String(),
            'author' => $post->author?->name,
        ];

        if ($includeBody) {
            $data['bodyHtml'] = $post->body_html;
        }

        return $data;
    }
}
