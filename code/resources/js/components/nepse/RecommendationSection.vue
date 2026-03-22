<script setup lang="ts">
import { computed } from 'vue';
import MetricTrend from '@/components/nepse/MetricTrend.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { RecommendationEntry, RecommendationSignalGroup } from '@/types';

const props = defineProps<{
    title: string;
    description: string;
    signals: RecommendationSignalGroup;
    metricOrder: string[];
    metricLabels: Record<string, string>;
    emptyMessage: string;
}>();

const sections = computed(() => [
    {
        key: 'buy',
        title: 'Buy',
        accent: 'border-emerald-500/40 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300',
        data: props.signals.buy,
    },
    {
        key: 'sell',
        title: 'Sell',
        accent: 'border-rose-500/40 bg-rose-500/5 text-rose-700 dark:text-rose-300',
        data: props.signals.sell,
    },
]);

function deltaEntries(recommendation: RecommendationEntry) {
    return Object.entries(recommendation.deltas).filter(([, value]) => value !== null);
}

function labelForMetric(metricKey: string, metricLabels: Record<string, string>) {
    return metricLabels[metricKey] ?? metricKey.toUpperCase();
}

function changeSinceDate(recommendation: RecommendationEntry) {
    if (recommendation.closeOnDate === null || recommendation.closeToday === null || recommendation.closeOnDate === 0) {
        return null;
    }

    return ((recommendation.closeToday - recommendation.closeOnDate) / recommendation.closeOnDate) * 100;
}
</script>

<template>
    <section class="space-y-4">
        <div class="space-y-1">
            <h2 class="text-2xl font-semibold tracking-tight">
                {{ title }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>

        <div v-if="signals.buy.length > 0 || signals.sell.length > 0" class="space-y-6">
            <section
                v-for="section in sections"
                :key="section.key"
                v-show="section.data.length > 0"
                class="space-y-3"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <h3 class="text-lg font-semibold tracking-tight">
                        {{ section.title }}
                    </h3>
                    <Badge variant="outline" :class="section.accent">
                        {{ section.data.length }} signal{{ section.data.length === 1 ? '' : 's' }}
                    </Badge>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <Card
                        v-for="recommendation in section.data"
                        :key="`${section.key}-${recommendation.symbol}`"
                        class="border-border/60"
                    >
                        <CardHeader class="gap-3">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <CardTitle class="text-lg">
                                        {{ recommendation.symbol }}
                                    </CardTitle>
                                    <CardDescription>
                                        {{ recommendation.companyName }}
                                    </CardDescription>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Badge variant="secondary">
                                        {{ recommendation.sector ?? 'Unknown sector' }}
                                    </Badge>
                                    <Badge variant="outline">
                                        {{ recommendation.asOfDate ?? 'No date' }}
                                    </Badge>
                                    <Badge v-if="recommendation.tradedSharePercent !== null" variant="outline">
                                        Liquidity {{ recommendation.tradedSharePercent.toFixed(2) }}%
                                    </Badge>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent class="space-y-4">
                            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                                <div class="rounded-lg bg-muted/40 p-3">
                                    <dt class="text-muted-foreground">Close on date</dt>
                                    <dd class="mt-1 font-medium">
                                        {{ recommendation.closeOnDate !== null ? recommendation.closeOnDate.toFixed(2) : 'N/A' }}
                                    </dd>
                                </div>
                                <div class="rounded-lg bg-muted/40 p-3">
                                    <dt class="text-muted-foreground">Latest close</dt>
                                    <dd class="mt-1 font-medium">
                                        {{ recommendation.closeToday !== null ? recommendation.closeToday.toFixed(2) : 'N/A' }}
                                    </dd>
                                </div>
                                <div class="rounded-lg bg-muted/40 p-3">
                                    <dt class="text-muted-foreground">Change since date</dt>
                                    <dd class="mt-1 font-medium">
                                        {{ changeSinceDate(recommendation) !== null ? `${changeSinceDate(recommendation)?.toFixed(2)}%` : 'N/A' }}
                                    </dd>
                                </div>
                                <div class="rounded-lg bg-muted/40 p-3">
                                    <dt class="text-muted-foreground">Stop loss</dt>
                                    <dd class="mt-1 font-medium">
                                        {{ recommendation.stopLoss !== null ? recommendation.stopLoss.toFixed(2) : 'N/A' }}
                                    </dd>
                                </div>
                            </dl>

                            <div
                                v-if="deltaEntries(recommendation).length > 0"
                                class="flex flex-wrap gap-2"
                            >
                                <Badge
                                    v-for="[metricKey, delta] in deltaEntries(recommendation)"
                                    :key="`${recommendation.symbol}-${metricKey}`"
                                    variant="outline"
                                >
                                    {{ labelForMetric(metricKey, metricLabels) }} Δ
                                    {{ Number(delta).toFixed(2) }}
                                </Badge>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <MetricTrend
                                    v-for="metricKey in metricOrder"
                                    :key="`${section.key}-${recommendation.symbol}-${metricKey}`"
                                    :label="metricLabels[metricKey] ?? metricKey"
                                    :metric="recommendation.metrics[metricKey]"
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </div>

        <Card v-else class="border-dashed">
            <CardContent class="py-10 text-center text-sm text-muted-foreground">
                {{ emptyMessage }}
            </CardContent>
        </Card>
    </section>
</template>
