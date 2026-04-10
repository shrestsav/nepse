<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, CircleDot, Edit3, ExternalLink, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, reactive } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { relativeBlogDate } from '@/lib/blog';
import { dashboard } from '@/routes';
import type { BlogAdminPostSummary, BreadcrumbItem } from '@/types';

const props = defineProps<{
    posts: BlogAdminPostSummary[];
    filters: {
        search: string | null;
        status: string | null;
    };
    statusCounts: Record<string, number>;
}>();

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Blog posts',
        href: '/dashboard/blog/posts',
    },
];

const summaryCards = computed(() => [
    { label: 'All posts', value: props.statusCounts.all ?? 0 },
    { label: 'Drafts', value: props.statusCounts.draft ?? 0 },
    { label: 'Published', value: props.statusCounts.published ?? 0 },
    { label: 'Archived', value: props.statusCounts.archived ?? 0 },
]);

function applyFilters(): void {
    router.get(
        '/dashboard/blog/posts',
        {
            search: filters.search || undefined,
            status: filters.status || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function publishPost(postId: number): void {
    router.post(`/dashboard/blog/posts/${postId}/publish`, {}, { preserveScroll: true });
}

function archivePost(postId: number): void {
    router.post(`/dashboard/blog/posts/${postId}/archive`, {}, { preserveScroll: true });
}

function deletePost(postId: number): void {
    if (typeof window !== 'undefined' && !window.confirm('Delete this blog post?')) {
        return;
    }

    router.delete(`/dashboard/blog/posts/${postId}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Blog posts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div class="space-y-1">
                    <h1 class="text-3xl font-semibold tracking-tight">Blog posts</h1>
                    <p class="text-sm text-muted-foreground">
                        Manage the public NEPSE news feed, publishing state, and article previews from the dashboard.
                    </p>
                </div>

                <Button as-child>
                    <Link href="/dashboard/blog/posts/create">
                        <Plus class="size-4" />
                        New post
                    </Link>
                </Button>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <div
                    v-for="card in summaryCards"
                    :key="card.label"
                    class="rounded-2xl border border-border/70 bg-card px-5 py-4 shadow-xs"
                >
                    <p class="text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">
                        {{ card.label }}
                    </p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight">
                        {{ card.value }}
                    </p>
                </div>
            </div>

            <section class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs">
                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_200px_auto]">
                    <label class="grid gap-2">
                        <span class="text-sm font-medium">Search</span>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="filters.search"
                                type="search"
                                class="h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                placeholder="Title, slug, excerpt"
                                @keydown.enter.prevent="applyFilters"
                            >
                        </div>
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-medium">Status</span>
                        <select
                            v-model="filters.status"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        >
                            <option value="">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="archived">Archived</option>
                        </select>
                    </label>

                    <div class="flex items-end gap-3">
                        <Button @click="applyFilters">Apply filters</Button>
                        <Button
                            variant="outline"
                            @click="
                                filters.search = '';
                                filters.status = '';
                                applyFilters();
                            "
                        >
                            Clear
                        </Button>
                    </div>
                </div>
            </section>

            <section
                v-if="posts.length > 0"
                class="grid gap-4 xl:grid-cols-2"
            >
                <article
                    v-for="post in posts"
                    :key="post.id"
                    class="rounded-2xl border border-border/70 bg-card p-5 shadow-xs"
                >
                    <div class="flex flex-col gap-5 md:flex-row md:items-start">
                        <div
                            class="h-36 w-full shrink-0 rounded-2xl bg-muted bg-cover bg-center md:w-44"
                            :style="
                                post.coverImageUrl
                                    ? { backgroundImage: `url(${post.coverImageUrl})` }
                                    : undefined
                            "
                        />

                        <div class="min-w-0 flex-1 space-y-4">
                            <div class="flex flex-wrap items-start gap-3">
                                <div class="min-w-0 flex-1 space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Badge variant="outline">
                                            {{ post.statusLabel }}
                                        </Badge>
                                        <Badge variant="secondary">
                                            {{ post.contentFormat === 'markdown' ? 'Markdown' : 'Rich text' }}
                                        </Badge>
                                    </div>
                                    <h2 class="text-xl font-semibold tracking-tight">
                                        {{ post.title }}
                                    </h2>
                                    <p class="text-sm text-muted-foreground">
                                        {{ post.excerpt || 'No excerpt added yet.' }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="tag in post.tags"
                                    :key="tag"
                                    class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground"
                                >
                                    {{ tag }}
                                </span>
                            </div>

                            <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-muted-foreground">
                                <span>
                                    Updated {{ relativeBlogDate(post.updatedAt) }}
                                </span>
                                <span v-if="post.publishedAt">
                                    Published {{ relativeBlogDate(post.publishedAt) }}
                                </span>
                                <span>
                                    {{ post.author ?? 'Unknown author' }}
                                </span>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Button variant="outline" as-child>
                                    <Link :href="`/dashboard/blog/posts/${post.id}/edit`">
                                        <Edit3 class="size-4" />
                                        Edit
                                    </Link>
                                </Button>

                                <Button
                                    v-if="post.status !== 'published'"
                                    @click="publishPost(post.id)"
                                >
                                    <CircleDot class="size-4" />
                                    Publish
                                </Button>

                                <Button
                                    v-if="post.status === 'published'"
                                    variant="outline"
                                    @click="archivePost(post.id)"
                                >
                                    <Archive class="size-4" />
                                    Archive
                                </Button>

                                <a
                                    v-if="post.publicUrl"
                                    :href="post.publicUrl"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="inline-flex h-9 items-center gap-2 rounded-md border border-border px-4 text-sm font-medium transition hover:bg-accent"
                                >
                                    <ExternalLink class="size-4" />
                                    View
                                </a>

                                <Button
                                    variant="destructive"
                                    @click="deletePost(post.id)"
                                >
                                    <Trash2 class="size-4" />
                                    Delete
                                </Button>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <section
                v-else
                class="rounded-2xl border border-border/70 bg-card px-8 py-16 text-center shadow-xs"
            >
                <h2 class="text-2xl font-semibold tracking-tight">No blog posts found</h2>
                <p class="mt-3 text-sm text-muted-foreground">
                    Try changing the filters or create the first NEPSE blog post.
                </p>
            </section>
        </div>
    </AppLayout>
</template>
