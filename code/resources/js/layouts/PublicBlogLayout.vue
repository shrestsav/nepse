<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, onUpdated, computed } from 'vue';
import BlogSearchButton from '@/components/blog/BlogSearchButton.vue';
import { dashboard, login } from '@/routes';

const props = defineProps<{
    isAuthenticated: boolean;
}>();

const navigationCta = computed(() => props.isAuthenticated ? dashboard() : login());
const navigationLabel = computed(() => props.isAuthenticated ? 'Dashboard' : 'Login');

let observer: IntersectionObserver | null = null;

function activateRevealAnimations(): void {
    if (typeof window === 'undefined') {
        return;
    }

    observer?.disconnect();

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const nodes = Array.from(document.querySelectorAll<HTMLElement>('[data-blog-reveal]'));

    if (reduceMotion) {
        nodes.forEach((node) => node.classList.add('is-visible'));

        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                (entry.target as HTMLElement).classList.add('is-visible');
                observer?.unobserve(entry.target);
            });
        },
        { threshold: 0, rootMargin: '50px 0px 50px 0px' },
    );

    nodes.forEach((node, index) => {
        node.style.setProperty('--reveal-delay', `${Math.min(index * 45, 360)}ms`);
        observer?.observe(node);
    });
}

onMounted(() => {
    activateRevealAnimations();
});

onUpdated(() => {
    void nextTick(() => {
        activateRevealAnimations();
    });
});

onBeforeUnmount(() => {
    observer?.disconnect();
});
</script>

<template>
    <div class="blog-site">
        <header class="site-header">
            <div class="blog-wrap header-inner">
                <Link href="/" class="brand" aria-label="NEPSE blog home">
                    <span class="brand-mark" />
                    <span class="brand-text">NEPSE</span>
                </Link>

                <nav class="top-nav" aria-label="Top navigation">
                    <Link href="/">Blog</Link>
                    <Link :href="navigationCta">{{ navigationLabel }}</Link>
                    <span class="nav-divider" />
                    <BlogSearchButton />
                </nav>
            </div>
        </header>

        <main class="site-main">
            <slot />
        </main>
    </div>
</template>
