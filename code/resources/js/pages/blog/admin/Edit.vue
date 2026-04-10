<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BlogPostEditorForm from '@/components/blog/BlogPostEditorForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BlogEditorPost, BlogOption, BreadcrumbItem } from '@/types';

const props = defineProps<{
    post: BlogEditorPost;
    statusOptions: BlogOption[];
    formatOptions: BlogOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Blog posts',
        href: '/dashboard/blog/posts',
    },
    {
        title: props.post.title,
        href: `/dashboard/blog/posts/${props.post.id}/edit`,
    },
];
</script>

<template>
    <Head :title="post.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="space-y-1">
                <h1 class="text-3xl font-semibold tracking-tight">{{ post.title }}</h1>
                <p class="text-sm text-muted-foreground">
                    Update the article, switch formats, and keep the public NEPSE feed in sync.
                </p>
            </div>

            <BlogPostEditorForm
                :post="props.post"
                :status-options="statusOptions"
                :format-options="formatOptions"
                :submit-url="`/dashboard/blog/posts/${post.id}`"
                submit-method="put"
                submit-label="Save changes"
                :delete-url="`/dashboard/blog/posts/${post.id}`"
            />
        </div>
    </AppLayout>
</template>
