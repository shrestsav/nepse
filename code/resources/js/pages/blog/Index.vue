<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import BlogHeroCarousel from '@/components/blog/BlogHeroCarousel.vue';
import BlogPostCard from '@/components/blog/BlogPostCard.vue';
import BlogPromoCard from '@/components/blog/BlogPromoCard.vue';
import PublicBlogLayout from '@/layouts/PublicBlogLayout.vue';
import type { BlogPublicPost } from '@/types';

const props = defineProps<{
    posts: BlogPublicPost[];
    isAuthenticated: boolean;
}>();

const featuredPosts = computed(() => props.posts.slice(0, 5));
const feedPosts = computed(() =>
    props.posts.length > 5 ? props.posts.slice(5) : props.posts.slice(1),
);
</script>

<template>
    <Head title="NEPSE Blog">
        <meta
            head-key="description"
            name="description"
            content="Public-facing NEPSE news, market wrap-ups, and trading commentary."
        >
    </Head>

    <PublicBlogLayout :is-authenticated="isAuthenticated">
        <div class="page">
            <div data-blog-reveal>
                <BlogHeroCarousel :posts="featuredPosts" />
            </div>

            <section v-if="feedPosts.length > 0" class="home-main" data-blog-reveal>
                <div class="home-layout-grid">
                    <div class="posts-column">
                        <div class="post-grid home-post-grid">
                            <BlogPostCard
                                v-for="post in feedPosts"
                                :key="post.id"
                                :post="post"
                            />
                        </div>
                    </div>

                    <aside class="sidebar-column">
                        <BlogPromoCard :is-authenticated="isAuthenticated" />
                    </aside>
                </div>
            </section>

            <section v-else class="rounded-[20px] border border-border/70 bg-card px-8 py-16 text-center" data-blog-reveal>
                <h2 class="text-2xl font-semibold tracking-tight text-foreground">
                    No stories are published yet.
                </h2>
                <p class="mt-3 text-sm text-muted-foreground">
                    Publish the first NEPSE update from the dashboard blog manager and it will appear here.
                </p>
            </section>
        </div>
    </PublicBlogLayout>
</template>
