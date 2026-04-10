<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BlogArticleContent from '@/components/blog/BlogArticleContent.vue';
import BlogPostCard from '@/components/blog/BlogPostCard.vue';
import PublicBlogLayout from '@/layouts/PublicBlogLayout.vue';
import {
    blogCategories,
    blogCoverImage,
    blogReadingTimeFromHtml,
    formatBlogDate,
} from '@/lib/blog';
import type { BlogPublicPost } from '@/types';

const props = defineProps<{
    post: BlogPublicPost;
    relatedPosts: BlogPublicPost[];
    isAuthenticated: boolean;
}>();

const categories = computed(() => blogCategories(props.post.tags));
</script>

<template>
    <Head :title="post.title">
        <meta
            head-key="description"
            name="description"
            :content="post.excerpt ?? 'NEPSE market analysis and commentary.'"
        >
    </Head>

    <PublicBlogLayout :is-authenticated="isAuthenticated">
        <div class="post-page">
            <section
                class="post-hero-section"
                data-blog-reveal
                :style="{ backgroundImage: `url(${blogCoverImage(post, true)})` }"
            >
                <div class="post-hero-overlay" />
                <div class="post-header-inner">
                    <div class="post-categories light-categories">
                        <span v-for="category in categories" :key="category">
                            {{ category }}
                        </span>
                    </div>

                    <h1>{{ post.title }}</h1>

                    <div class="post-meta-row light-meta">
                        <div class="author-info">
                            <span class="author">
                                {{ post.author ?? 'Editorial Team' }}
                            </span>
                            <span class="dot">·</span>
                            <span>{{ formatBlogDate(post.publishedAt) }}</span>
                        </div>
                        <span class="readtime">
                            {{ blogReadingTimeFromHtml(post.bodyHtml) }} min read
                        </span>
                    </div>
                </div>
            </section>

            <section class="article-main" data-blog-reveal>
                <article class="article-shell">
                    <p v-if="post.excerpt" class="lead">{{ post.excerpt }}</p>

                    <div v-if="post.tags.length > 0" class="tags">
                        <span v-for="tag in post.tags" :key="tag" class="tag">
                            {{ tag }}
                        </span>
                    </div>

                    <BlogArticleContent :html="post.bodyHtml ?? ''" />

                    <section v-if="post.sourceUrls.length > 0" class="sources">
                        <h3>Sources</h3>
                        <ul>
                            <li v-for="(url, index) in post.sourceUrls" :key="`${url}-${index}`">
                                <a :href="url" target="_blank" rel="noreferrer">
                                    {{ url }}
                                </a>
                            </li>
                        </ul>
                    </section>

                    <p class="back-link">
                        <Link href="/">← Back to all posts</Link>
                    </p>
                </article>
            </section>

            <section
                v-if="relatedPosts.length > 0"
                class="related-section"
                data-blog-reveal
            >
                <div class="section-header">
                    <h2>Related</h2>
                    <span class="line" />
                </div>

                <div class="post-grid related-grid">
                    <BlogPostCard
                        v-for="relatedPost in relatedPosts"
                        :key="relatedPost.id"
                        :post="relatedPost"
                    />
                </div>
            </section>
        </div>
    </PublicBlogLayout>
</template>
