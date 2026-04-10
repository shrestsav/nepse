<?php

namespace Database\Factories;

use App\Enums\BlogContentFormat;
use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function configure(): static
    {
        return $this->afterCreating(function (BlogPost $blogPost): void {
            $tagIds = collect(['nepse', 'market update'])
                ->map(fn (string $tag): int => BlogTag::query()->firstOrCreate(['name' => $tag])->id)
                ->all();

            $blogPost->tags()->sync($tagIds);
        });
    }

    public function definition(): array
    {
        $title = fake()->sentence(6);
        $bodySource = implode("\n\n", [
            '## Market snapshot',
            fake()->paragraph(4),
            '### Why it matters',
            fake()->paragraph(3),
        ]);

        return [
            'user_id' => User::factory(),
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 999),
            'title' => $title,
            'excerpt' => fake()->sentence(18),
            'content_format' => BlogContentFormat::Markdown,
            'body_source' => $bodySource,
            'body_html' => '<h2>Market snapshot</h2><p>'.e(fake()->paragraph(3)).'</p>',
            'source_urls' => ['https://example.com/news'],
            'cover_image_path' => null,
            'cover_image_url' => 'https://picsum.photos/seed/'.fake()->uuid().'/960/640',
            'status' => BlogPostStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => BlogPostStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function richText(): static
    {
        return $this->state(fn (): array => [
            'content_format' => BlogContentFormat::RichText,
            'body_source' => '<h2>Market snapshot</h2><p>'.e(fake()->paragraph(3)).'</p>',
            'body_html' => '<h2>Market snapshot</h2><p>'.e(fake()->paragraph(3)).'</p>',
        ]);
    }
}
