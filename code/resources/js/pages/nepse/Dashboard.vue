<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import StatCard from '@/components/nepse/StatCard.vue';
import SyncLogPanel from '@/components/nepse/SyncLogPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import type { BreadcrumbItem, SyncLogSummary } from '@/types';

defineProps<{
    counts: {
        stocks: number;
        sectors: number;
        priceHistories: number;
    };
    recommendationCounts: {
        rsiAdx: number;
        rsiMacd: number;
        maEmaAdx: number;
    };
    recommendationDate: string | null;
    latestSync: SyncLogSummary | null;
    currentSync: SyncLogSummary | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];
</script>

<template>
    <Head title="NEPSE dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div
                v-if="recommendationDate"
                class="rounded-xl border border-border/60 bg-card px-4 py-3 text-sm text-muted-foreground"
            >
                Dashboard recommendation counts are calculated from the latest available trading date:
                <span class="font-medium text-foreground">{{ recommendationDate }}</span>
            </div>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    title="Tracked stocks"
                    :value="counts.stocks"
                    description="Symbols imported into the Laravel 13 NEPSE workspace."
                    :href="dashboardRoutes.stocks()"
                />
                <StatCard
                    title="Sectors"
                    :value="counts.sectors"
                    description="Unique market sectors discovered during catalog sync."
                />
                <StatCard
                    title="Price history rows"
                    :value="counts.priceHistories"
                    description="Historical rows currently available for analytics."
                    :href="dashboardRoutes.sync()"
                />
                <StatCard
                    title="Recommendation sets"
                    :value="recommendationCounts.rsiAdx + recommendationCounts.rsiMacd + recommendationCounts.maEmaAdx"
                    description="Combined matches across the three technical strategies."
                    :href="dashboardRoutes.recommendations()"
                />
            </section>

            <section class="grid gap-4 xl:grid-cols-3">
                <StatCard
                    title="RSI + ADX"
                    :value="recommendationCounts.rsiAdx"
                    description="Momentum plus trend-strength matches."
                    :href="dashboardRoutes.recommendations()"
                />
                <StatCard
                    title="RSI + MACD"
                    :value="recommendationCounts.rsiMacd"
                    description="Momentum plus crossover-style matches."
                    :href="dashboardRoutes.recommendations()"
                />
                <StatCard
                    title="MA/EMA + ADX"
                    :value="recommendationCounts.maEmaAdx"
                    description="Trend continuation setups using EMA and ADX."
                    :href="dashboardRoutes.recommendations()"
                />
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                <SyncLogPanel
                    title="Current sync"
                    description="Any running or queued NEPSE sync job."
                    :sync-log="currentSync"
                />
                <SyncLogPanel
                    title="Latest completed sync"
                    description="Most recent finished sync, including partial failures."
                    :sync-log="latestSync"
                />
            </section>
        </div>
    </AppLayout>
</template>
