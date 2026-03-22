<script setup lang="ts">
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-vue-next';
import { computed } from 'vue';
import MetricSparkline from '@/components/nepse/MetricSparkline.vue';
import type { RecommendationMetric } from '@/types';

const props = defineProps<{
    label: string;
    metric: RecommendationMetric;
}>();

const recentValues = computed(() => props.metric.recent);

function direction(current: number, previous?: number) {
    if (previous === undefined || current === previous) {
        return Minus;
    }

    return current > previous ? ArrowUpRight : ArrowDownRight;
}
</script>

<template>
    <div class="space-y-3 rounded-xl border border-border/60 p-4">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-foreground">
                {{ label }}
            </p>
            <span class="text-sm font-semibold">
                {{ metric.latest.toFixed(2) }}
            </span>
        </div>

        <MetricSparkline :values="metric.series" />

        <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <template v-for="(value, index) in recentValues" :key="`${label}-${index}`">
                <span class="rounded-full bg-muted px-2.5 py-1">
                    {{ value.toFixed(2) }}
                </span>
                <component
                    :is="direction(value, recentValues[index - 1])"
                    v-if="index > 0"
                    class="size-3.5"
                />
            </template>
        </div>
    </div>
</template>
