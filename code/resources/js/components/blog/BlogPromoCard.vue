<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { dashboard, login } from '@/routes';

const props = defineProps<{
    isAuthenticated: boolean;
}>();

const cta = computed(() => props.isAuthenticated ? dashboard() : login());
const ctaLabel = computed(() => props.isAuthenticated ? 'Open Dashboard' : 'Login to Manage');
</script>

<template>
    <div class="sidebar-widget promo-widget" data-blog-reveal>
        <div class="promo-brand">
            <span class="promo-brand-mark" />
            <span class="promo-brand-text">NEPSE</span>
        </div>
        <h3>Follow the market, not the noise.</h3>
        <p>
            Publish NEPSE-focused updates, trend breakdowns, and headline analysis
            from the same workspace you already use for the dashboard.
        </p>
        <Link :href="cta" class="promo-btn">
            {{ ctaLabel }}
        </Link>
    </div>
</template>
