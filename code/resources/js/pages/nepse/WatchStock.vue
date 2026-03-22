<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import WatchStockWorkspace from '@/components/nepse/WatchStockWorkspace.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import type { BreadcrumbItem, WatchStockOption } from '@/types';

defineProps<{
    stocks: WatchStockOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Watch stock',
        href: dashboardRoutes.watchStock(),
    },
];
</script>

<template>
    <Head title="Watch stock" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <WatchStockWorkspace :stocks="stocks" />
        </div>
    </AppLayout>
</template>
