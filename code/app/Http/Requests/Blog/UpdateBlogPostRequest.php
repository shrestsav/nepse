<?php

namespace App\Http\Requests\Blog;

use App\Enums\BlogContentFormat;
use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var BlogPost $blogPost */
        $blogPost = $this->route('blogPost');

        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'required',
                'string',
                'max:200',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_posts', 'slug')->ignore($blogPost),
            ],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content_format' => ['required', Rule::enum(BlogContentFormat::class)],
            'body_source' => ['required', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'source_urls' => ['nullable', 'array'],
            'source_urls.*' => ['url', 'max:1000'],
            'cover_image' => ['nullable', 'image', 'max:4096'],
            'cover_image_url' => ['nullable', 'url', 'max:1000'],
            'remove_cover_image' => ['nullable', 'boolean'],
            'status' => ['required', Rule::enum(BlogPostStatus::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('title'))),
            'tags' => $this->normalizeList($this->input('tags')),
            'source_urls' => $this->normalizeList($this->input('source_urls')),
            'remove_cover_image' => $this->boolean('remove_cover_image'),
            'excerpt' => blank($this->input('excerpt')) ? null : trim((string) $this->input('excerpt')),
            'cover_image_url' => blank($this->input('cover_image_url')) ? null : trim((string) $this->input('cover_image_url')),
        ]);
    }

    /**
     * @return list<string>
     */
    private function normalizeList(mixed $value): array
    {
        $items = match (true) {
            is_array($value) => Arr::flatten($value),
            is_string($value) => preg_split('/[\r\n,]+/', $value) ?: [],
            default => [],
        };

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $items,
        ))));
    }
}
