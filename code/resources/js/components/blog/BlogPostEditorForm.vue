<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Eye, FileText, Globe, ImagePlus, PenSquare, Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import BlogPostPreview from '@/components/blog/BlogPostPreview.vue';
import BlogRichTextEditor from '@/components/blog/BlogRichTextEditor.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { joinBlogList, splitBlogList } from '@/lib/blog';
import { convertBlogContentMode, renderBlogPreviewHtml } from '@/lib/blog-content';
import type {
    BlogContentFormat,
    BlogEditorPost,
    BlogOption,
    BlogPublicPost,
} from '@/types';

type Props = {
    post: BlogEditorPost;
    statusOptions: BlogOption[];
    formatOptions: BlogOption[];
    submitUrl: string;
    submitMethod: 'post' | 'put';
    submitLabel: string;
    deleteUrl?: string;
};

const props = defineProps<Props>();

const form = useForm({
    title: props.post.title,
    slug: props.post.slug,
    excerpt: props.post.excerpt,
    content_format: props.post.contentFormat,
    body_source: props.post.bodySource,
    tags: props.post.tags,
    source_urls: props.post.sourceUrls,
    cover_image_url: props.post.coverImageUrl ?? '',
    cover_image: null as File | null,
    remove_cover_image: false,
    status: props.post.status,
});

const tagsInput = ref(joinBlogList(props.post.tags));
const sourceUrlsInput = ref(joinBlogList(props.post.sourceUrls));
const slugTouched = ref(props.post.id !== null);
const filePreviewUrl = ref<string | null>(null);

watch(
    () => form.title,
    (title) => {
        if (!slugTouched.value) {
            form.slug = toSlug(title);
        }
    },
);

watch(
    () => form.cover_image,
    (file) => {
        if (filePreviewUrl.value && typeof window !== 'undefined') {
            window.URL.revokeObjectURL(filePreviewUrl.value);
            filePreviewUrl.value = null;
        }

        if (file && typeof window !== 'undefined') {
            filePreviewUrl.value = window.URL.createObjectURL(file);
        }
    },
);

onBeforeUnmount(() => {
    if (filePreviewUrl.value && typeof window !== 'undefined') {
        window.URL.revokeObjectURL(filePreviewUrl.value);
    }
});

const previewPost = computed<BlogPublicPost>(() => ({
    id: props.post.id ?? 0,
    slug: form.slug || 'preview-post',
    title: form.title || 'Untitled post',
    excerpt: form.excerpt || null,
    tags: splitBlogList(tagsInput.value),
    sourceUrls: splitBlogList(sourceUrlsInput.value),
    coverImageUrl: filePreviewUrl.value || form.cover_image_url || null,
    publishedAt:
        form.status === 'published'
            ? props.post.publishedAt ?? new Date().toISOString()
            : props.post.publishedAt,
    author: props.post.author,
    bodyHtml: renderBlogPreviewHtml(
        form.content_format as BlogContentFormat,
        form.body_source,
    ),
}));

async function switchMode(nextMode: BlogContentFormat): Promise<void> {
    if (nextMode === form.content_format) {
        return;
    }

    form.body_source = convertBlogContentMode(
        form.body_source,
        form.content_format as BlogContentFormat,
        nextMode,
    );
    form.content_format = nextMode;
}

function handleSlugInput(value: string | number): void {
    slugTouched.value = true;
    form.slug = toSlug(String(value));
}

function handleCoverUpload(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    form.cover_image = file;

    if (file) {
        form.remove_cover_image = false;
    }
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        slug: toSlug(data.slug || data.title),
        tags: splitBlogList(tagsInput.value),
        source_urls: splitBlogList(sourceUrlsInput.value),
        ...(props.submitMethod === 'put' ? { _method: 'put' } : {}),
    }));

    form.post(props.submitUrl, {
        forceFormData: true,
        preserveScroll: true,
    });
}

function destroyPost(): void {
    if (!props.deleteUrl) {
        return;
    }

    if (typeof window !== 'undefined' && !window.confirm('Delete this post?')) {
        return;
    }

    form.delete(props.deleteUrl, {
        preserveScroll: true,
    });
}

function toSlug(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
</script>

<template>
    <div class="grid gap-8 xl:grid-cols-[minmax(0,1.15fr)_minmax(360px,0.85fr)]">
        <div class="space-y-6">
            <section class="rounded-2xl border border-border/70 bg-card p-6 shadow-xs">
                <div class="space-y-2">
                    <h2 class="text-xl font-semibold tracking-tight">Post setup</h2>
                    <p class="text-sm text-muted-foreground">
                        Manage metadata, editor mode, sources, and the public cover image.
                    </p>
                </div>

                <div class="mt-6 grid gap-5">
                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input
                            id="title"
                            v-model="form.title"
                            placeholder="NEPSE weekly market wrap"
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="grid gap-2 md:grid-cols-[minmax(0,1fr)_220px] md:items-start md:gap-4">
                        <div class="grid gap-2">
                            <Label for="slug">Slug</Label>
                            <Input
                                id="slug"
                                :model-value="form.slug"
                                placeholder="nepse-weekly-market-wrap"
                                @update:model-value="handleSlugInput"
                            />
                            <InputError :message="form.errors.slug" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="status">Status</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="excerpt">Excerpt</Label>
                        <textarea
                            id="excerpt"
                            v-model="form.excerpt"
                            rows="3"
                            class="min-h-[96px] rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            placeholder="A short summary that appears on the feed and in meta previews."
                        />
                        <InputError :message="form.errors.excerpt" />
                    </div>

                    <div class="grid gap-2 md:grid-cols-2 md:items-start md:gap-4">
                        <div class="grid gap-2">
                            <Label for="tags">Tags</Label>
                            <textarea
                                id="tags"
                                v-model="tagsInput"
                                rows="4"
                                class="min-h-[112px] rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                placeholder="nepse&#10;banking&#10;market wrap"
                            />
                            <p class="text-xs text-muted-foreground">
                                One tag per line or comma-separated.
                            </p>
                            <InputError :message="form.errors.tags" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="source-urls">Source URLs</Label>
                            <textarea
                                id="source-urls"
                                v-model="sourceUrlsInput"
                                rows="4"
                                class="min-h-[112px] rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                placeholder="https://example.com/source"
                            />
                            <p class="text-xs text-muted-foreground">
                                One URL per line. These are rendered on the public article page.
                            </p>
                            <InputError :message="form.errors.source_urls" />
                        </div>
                    </div>

                    <div class="grid gap-2 md:grid-cols-2 md:items-start md:gap-4">
                        <div class="grid gap-2">
                            <Label for="cover-image-url">Cover image URL</Label>
                            <Input
                                id="cover-image-url"
                                v-model="form.cover_image_url"
                                placeholder="https://..."
                            />
                            <p class="text-xs text-muted-foreground">
                                Used when no uploaded cover is present.
                            </p>
                            <InputError :message="form.errors.cover_image_url" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="cover-image">Upload cover image</Label>
                            <label
                                class="flex h-24 cursor-pointer items-center justify-center rounded-xl border border-dashed border-border bg-muted/20 px-4 text-center text-sm text-muted-foreground transition hover:border-ring hover:bg-muted/40"
                            >
                                <input
                                    id="cover-image"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="handleCoverUpload"
                                >
                                <span class="flex items-center gap-2">
                                    <ImagePlus class="size-4" />
                                    {{ form.cover_image ? form.cover_image.name : 'Choose an image' }}
                                </span>
                            </label>
                            <div
                                v-if="post.coverImagePath && !form.cover_image"
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <input
                                    id="remove-cover-image"
                                    v-model="form.remove_cover_image"
                                    type="checkbox"
                                    class="size-4 rounded border-border"
                                >
                                <Label
                                    for="remove-cover-image"
                                    class="cursor-pointer text-xs font-normal text-muted-foreground"
                                >
                                    Remove the uploaded cover and fall back to the URL.
                                </Label>
                            </div>
                            <InputError :message="form.errors.cover_image" />
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-border/70 bg-card p-6 shadow-xs">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="space-y-2">
                        <h2 class="text-xl font-semibold tracking-tight">Write the story</h2>
                        <p class="text-sm text-muted-foreground">
                            Switch between Markdown and rich text. The preview uses the same rendered HTML structure as the public article.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            :variant="form.content_format === 'markdown' ? 'default' : 'outline'"
                            size="sm"
                            @click="switchMode('markdown')"
                        >
                            <FileText class="size-4" />
                            Markdown
                        </Button>
                        <Button
                            type="button"
                            :variant="form.content_format === 'rich_text' ? 'default' : 'outline'"
                            size="sm"
                            @click="switchMode('rich_text')"
                        >
                            <PenSquare class="size-4" />
                            Rich text
                        </Button>
                    </div>
                </div>

                <div class="mt-6 grid gap-2">
                    <Label>Content</Label>
                    <textarea
                        v-if="form.content_format === 'markdown'"
                        v-model="form.body_source"
                        rows="18"
                        class="min-h-[440px] rounded-xl border border-border/70 bg-background px-4 py-3 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        placeholder="## Market snapshot"
                    />
                    <BlogRichTextEditor
                        v-else
                        v-model="form.body_source"
                    />
                    <InputError :message="form.errors.body_source" />
                </div>
            </section>

            <div class="flex flex-wrap items-center gap-3">
                <Button :disabled="form.processing" @click="submit">
                    {{ submitLabel }}
                </Button>

                <a
                    v-if="post.publicUrl"
                    :href="post.publicUrl"
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex h-9 items-center gap-2 rounded-md border border-border px-4 text-sm font-medium transition hover:bg-accent"
                >
                    <Globe class="size-4" />
                    View public post
                </a>

                <Button
                    v-if="deleteUrl"
                    type="button"
                    variant="destructive"
                    :disabled="form.processing"
                    @click="destroyPost"
                >
                    <Trash2 class="size-4" />
                    Delete post
                </Button>
            </div>
        </div>

        <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
            <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <Eye class="size-4" />
                Live preview
            </div>
            <BlogPostPreview :post="previewPost" />
        </aside>
    </div>
</template>
