<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { blogCategories, blogCoverImage, blogExcerpt, relativeBlogDate } from '@/lib/blog';
import type { BlogPublicPost } from '@/types';

const props = defineProps<{
    post: BlogPublicPost;
}>();

const categories = computed(() => blogCategories(props.post.tags));
const excerpt = computed(() => blogExcerpt(props.post.excerpt, props.post.bodyHtml));
const href = computed(() => `/blog/${props.post.slug}`);
</script>

<template>
    <article class="post-card" data-blog-reveal>
        <div
            class="post-thumb"
            :style="{ backgroundImage: `url(${blogCoverImage(post)})` }"
        >
            <Link
                :href="href"
                :aria-label="`Open ${post.title}`"
                class="media-overlay"
            />
        </div>

        <div class="post-body">
            <div class="post-categories small">
                <span v-for="category in categories" :key="category">
                    {{ category }}
                </span>
            </div>

            <Link :href="href" class="post-title-link">
                <h2>{{ post.title }}</h2>
            </Link>

            <p>{{ excerpt }}</p>

            <div class="post-meta-row">
                <span class="author">{{ post.author ?? 'Editorial' }}</span>
                <span class="dot">·</span>
                <span>{{ relativeBlogDate(post.publishedAt) }}</span>
            </div>
        </div>
    </article>
</template>
