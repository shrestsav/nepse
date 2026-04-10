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
        title: 'New post',
        href: '/dashboard/blog/posts/create',
    },
];
</script>

<template>
    <Head title="New blog post" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="space-y-1">
                <h1 class="text-3xl font-semibold tracking-tight">New blog post</h1>
                <p class="text-sm text-muted-foreground">
                    Draft a new public-facing NEPSE story, preview it live, and publish when it is ready.
                </p>
            </div>

            <BlogPostEditorForm
                :post="props.post"
                :status-options="statusOptions"
                :format-options="formatOptions"
                submit-url="/dashboard/blog/posts"
                submit-method="post"
                submit-label="Create post"
            />
        </div>
    </AppLayout>
</template>
