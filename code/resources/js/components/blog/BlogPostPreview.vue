<script setup lang="ts">
import { computed } from 'vue';
import BlogArticleContent from '@/components/blog/BlogArticleContent.vue';
import {
    blogCategories,
    blogCoverImage,
    blogExcerpt,
    blogReadingTimeFromHtml,
    formatBlogDate,
} from '@/lib/blog';
import type { BlogPublicPost } from '@/types';

const props = defineProps<{
    post: BlogPublicPost;
}>();

const categories = computed(() => blogCategories(props.post.tags));
const excerpt = computed(() => blogExcerpt(props.post.excerpt, props.post.bodyHtml));
</script>

<template>
    <div class="blog-preview-panel">
        <div
            class="blog-preview-cover"
            :style="{ backgroundImage: `url(${blogCoverImage(post, true)})` }"
        />
        <div class="blog-preview-body">
            <div class="post-categories">
                <span v-for="category in categories" :key="category">
                    {{ category }}
                </span>
            </div>

            <h2 class="blog-preview-title">{{ post.title || 'Untitled post' }}</h2>

            <div class="post-meta-row">
                <span class="author">{{ post.author ?? 'Editorial Team' }}</span>
                <span class="dot">·</span>
                <span>{{ formatBlogDate(post.publishedAt) }}</span>
                <span class="readtime">
                    {{ blogReadingTimeFromHtml(post.bodyHtml) }} min read
                </span>
            </div>

            <p class="blog-preview-excerpt">{{ excerpt }}</p>

            <BlogArticleContent
                :html="post.bodyHtml || '<p>Preview will appear here as you write.</p>'"
            />
        </div>
    </div>
</template>
