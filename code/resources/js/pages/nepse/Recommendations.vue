<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarRange } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import RecommendationSection from '@/components/nepse/RecommendationSection.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import type { BreadcrumbItem, RecommendationGroups, SyncLogSummary } from '@/types';

const props = defineProps<{
    groups: RecommendationGroups;
    selectedDate: string | null;
    requestedDate: string | null;
    dateBounds: {
        min: string | null;
        max: string | null;
    };
    latestSync: SyncLogSummary | null;
}>();

const dateInput = ref(props.selectedDate ?? '');

watch(
    () => props.selectedDate,
    (value) => {
        dateInput.value = value ?? '';
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Recommendations',
        href: dashboardRoutes.recommendations(),
    },
];

function applyDate() {
    const query = dateInput.value ? { date: dateInput.value } : {};

    router.get('/dashboard/recommendations', query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function useLatestDate() {
    dateInput.value = props.dateBounds.max ?? '';
    applyDate();
}
</script>

<template>
    <Head title="Recommendations" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <h1 class="text-3xl font-semibold tracking-tight">
                        Recommendation engine
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Browse NEPSE signals for a specific market date and compare them with the latest stored close.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Badge v-if="props.selectedDate" variant="outline">
                        As of {{ props.selectedDate }}
                    </Badge>
                    <Badge v-if="props.latestSync" variant="outline">
                        Latest sync: {{ props.latestSync.typeLabel }} / {{ props.latestSync.status }}
                    </Badge>
                </div>
            </div>

            <div class="rounded-2xl border border-border/60 bg-card p-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-56 flex-1 space-y-2">
                        <label class="text-sm font-medium">Recommendation date</label>
                        <div class="relative">
                            <CalendarRange class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="dateInput"
                                type="date"
                                :min="props.dateBounds.min ?? undefined"
                                :max="props.dateBounds.max ?? undefined"
                                class="pl-9"
                            />
                        </div>
                    </div>
                    <Button @click="applyDate">
                        Apply date
                    </Button>
                    <Button variant="outline" @click="useLatestDate">
                        Latest available
                    </Button>
                </div>

                <p
                    v-if="props.requestedDate && props.selectedDate && props.requestedDate !== props.selectedDate"
                    class="mt-3 text-sm text-muted-foreground"
                >
                    No trading data was available for {{ props.requestedDate }}. Showing the nearest prior trading date instead.
                </p>
            </div>

            <RecommendationSection
                title="RSI + ADX"
                description="Later-branch watchlist logic with buy and sell buckets based on RSI momentum and ADX trend strength."
                :signals="props.groups.rsiAdx"
                :metric-order="['rsi', 'adx']"
                :metric-labels="{ rsi: 'RSI', adx: 'ADX' }"
                empty-message="No RSI + ADX signals are available for the selected date."
            />

            <RecommendationSection
                title="RSI + MACD"
                description="Momentum candidates where both RSI and MACD are improving together."
                :signals="props.groups.rsiMacd"
                :metric-order="['rsi', 'macd']"
                :metric-labels="{ rsi: 'RSI', macd: 'MACD' }"
                empty-message="No RSI + MACD signals are available for the selected date."
            />

            <RecommendationSection
                title="MA / EMA + ADX"
                description="Trend continuation signals using EMA highs/lows plus ADX, with stop-loss guidance from EMA low."
                :signals="props.groups.maEmaAdx"
                :metric-order="['emaHigh', 'emaLow', 'emaHlc3', 'adx']"
                :metric-labels="{ emaHigh: 'EMA High', emaLow: 'EMA Low', emaHlc3: 'EMA HLC3', adx: 'ADX' }"
                empty-message="No MA / EMA + ADX signals are available for the selected date."
            />
        </div>
    </AppLayout>
</template>
