<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { BacktestRunSummary } from '@/types';

defineProps<{
    title: string;
    description: string;
    run: BacktestRunSummary | null;
}>();

function formatDuration(durationSeconds: number | null) {
    if (durationSeconds === null) {
        return 'In progress';
    }

    if (durationSeconds >= 60) {
        return `${Math.round(durationSeconds / 60)} min`;
    }

    return `${durationSeconds} sec`;
}
</script>

<template>
    <Card class="h-full border-border/60">
        <CardHeader class="space-y-1">
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription>{{ description }}</CardDescription>
        </CardHeader>
        <CardContent v-if="run" class="space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <Badge>{{ run.strategyLabel ?? run.strategy }}</Badge>
                <Badge variant="outline">{{ run.statusLabel ?? run.status }}</Badge>
            </div>

            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-lg bg-muted/40 p-3">
                    <dt class="text-muted-foreground">Range</dt>
                    <dd class="mt-1 font-medium">
                        {{ run.startDate }} to {{ run.endDate }}
                    </dd>
                </div>
                <div class="rounded-lg bg-muted/40 p-3">
                    <dt class="text-muted-foreground">Duration</dt>
                    <dd class="mt-1 font-medium">
                        {{ formatDuration(run.durationSeconds) }}
                    </dd>
                </div>
                <div class="rounded-lg bg-muted/40 p-3">
                    <dt class="text-muted-foreground">Eligible stocks</dt>
                    <dd class="mt-1 font-medium">
                        {{ run.eligibleStockCount }}
                    </dd>
                </div>
                <div class="rounded-lg bg-muted/40 p-3">
                    <dt class="text-muted-foreground">Total trades</dt>
                    <dd class="mt-1 font-medium">
                        {{ run.totalTrades }}
                    </dd>
                </div>
                <div class="rounded-lg bg-muted/40 p-3">
                    <dt class="text-muted-foreground">Win / loss</dt>
                    <dd class="mt-1 font-medium">
                        {{ run.wins }} / {{ run.losses }}
                    </dd>
                </div>
                <div class="rounded-lg bg-muted/40 p-3">
                    <dt class="text-muted-foreground">Success rate</dt>
                    <dd class="mt-1 font-medium">
                        {{ run.successRate !== null ? `${run.successRate.toFixed(2)}%` : 'N/A' }}
                    </dd>
                </div>
            </dl>

            <div
                v-if="run.errorSummary"
                class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
            >
                {{ run.errorSummary }}
            </div>
        </CardContent>
        <CardContent v-else class="py-10 text-center text-sm text-muted-foreground">
            No backtest activity recorded yet.
        </CardContent>
    </Card>
</template>
