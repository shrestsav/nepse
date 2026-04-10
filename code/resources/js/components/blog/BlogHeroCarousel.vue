<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    blogCategories,
    blogCoverImage,
    blogExcerpt,
    blogReadingTimeFromHtml,
    relativeBlogDate,
} from '@/lib/blog';
import type { BlogPublicPost } from '@/types';

const props = defineProps<{
    posts: BlogPublicPost[];
}>();

const currentIndex = ref(0);
const fadeKey = ref(0);
let intervalId: number | null = null;

const featuredPost = computed(() => props.posts[currentIndex.value] ?? null);

function slideTo(index: number): void {
    currentIndex.value = index;
    fadeKey.value += 1;
}

function handleNext(): void {
    if (props.posts.length === 0) {
        return;
    }

    slideTo((currentIndex.value + 1) % props.posts.length);
}

function handlePrevious(): void {
    if (props.posts.length === 0) {
        return;
    }

    slideTo((currentIndex.value - 1 + props.posts.length) % props.posts.length);
}

function startInterval(): void {
    if (intervalId !== null || props.posts.length <= 1 || typeof window === 'undefined') {
        return;
    }

    intervalId = window.setInterval(() => {
        handleNext();
    }, 12000);
}

function stopInterval(): void {
    if (intervalId !== null && typeof window !== 'undefined') {
        window.clearInterval(intervalId);
        intervalId = null;
    }
}

watch(
    () => props.posts.length,
    () => {
        currentIndex.value = 0;
        stopInterval();
        startInterval();
    },
);

onMounted(() => {
    startInterval();
});

onBeforeUnmount(() => {
    stopInterval();
});
</script>

<template>
    <section v-if="featuredPost" class="feature-row">
        <div
            :key="`image-${fadeKey}`"
            class="feature-image carousel-fade"
            :style="{ backgroundImage: `url(${blogCoverImage(featuredPost, true)})` }"
        >
            <Link
                :href="`/blog/${featuredPost.slug}`"
                :aria-label="`Open ${featuredPost.title}`"
                class="media-overlay"
            />
        </div>

        <article class="feature-info">
            <div class="feature-controls">
                <button
                    type="button"
                    class="control-btn"
                    aria-label="Previous"
                    @click="handlePrevious"
                >
                    <svg
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <line x1="19" y1="12" x2="5" y2="12" />
                        <polyline points="12 19 5 12 12 5" />
                    </svg>
                </button>
                <button
                    type="button"
                    class="control-btn"
                    aria-label="Next"
                    @click="handleNext"
                >
                    <svg
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <line x1="5" y1="12" x2="19" y2="12" />
                        <polyline points="12 5 19 12 12 19" />
                    </svg>
                </button>
            </div>

            <div :key="`info-${fadeKey}`" class="carousel-fade">
                <div class="post-categories">
                    <span
                        v-for="category in blogCategories(featuredPost.tags)"
                        :key="category"
                    >
                        {{ category }}
                    </span>
                </div>

                <Link
                    :href="`/blog/${featuredPost.slug}`"
                    class="feature-title-link"
                >
                    <h1>{{ featuredPost.title }}</h1>
                </Link>

                <p class="feature-excerpt">
                    {{ blogExcerpt(featuredPost.excerpt, featuredPost.bodyHtml) }}
                </p>

                <div class="post-meta-row">
                    <span class="author">
                        {{ featuredPost.author ?? 'Editorial' }}
                    </span>
                    <span class="dot">·</span>
                    <span>{{ relativeBlogDate(featuredPost.publishedAt) }}</span>
                    <span class="readtime">
                        {{ blogReadingTimeFromHtml(featuredPost.bodyHtml) }} min
                    </span>
                </div>
            </div>
        </article>
    </section>
</template>
