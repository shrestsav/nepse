<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { SyncLogSummary } from '@/types';

defineProps<{
    title: string;
    description: string;
    syncLog: SyncLogSummary | null;
}>();

function formatDuration(totalTime: number | null) {
    if (totalTime === null) {
        return 'In progress';
    }

    if (totalTime >= 60) {
        return `${Math.round(totalTime / 60)} min`;
    }

    return `${totalTime} sec`;
}

function progress(syncLog: SyncLogSummary) {
    if (syncLog.totalStocks === 0) {
        return 0;
    }

    return Math.round((syncLog.processedStocks / syncLog.totalStocks) * 100);
}
</script>

<template>
    <Card class="h-full border-border/60">
        <CardHeader class="space-y-1">
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription>{{ description }}</CardDescription>
        </CardHeader>
        <CardContent v-if="syncLog" class="space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <Badge>{{ syncLog.typeLabel ?? syncLog.type }}</Badge>
                <Badge variant="outline">{{ syncLog.status }}</Badge>
            </div>

            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-lg bg-muted/40 p-3">
                    <dt class="text-muted-foreground">Started</dt>
                    <dd class="mt-1 font-medium">
                        {{ syncLog.start ? new Date(syncLog.start).toLocaleString() : 'Not started' }}
                    </dd>
                </div>
                <div class="rounded-lg bg-muted/40 p-3">
                    <dt class="text-muted-foreground">Duration</dt>
                    <dd class="mt-1 font-medium">
                        {{ formatDuration(syncLog.totalTime) }}
                    </dd>
                </div>
                <div class="rounded-lg bg-muted/40 p-3">
                    <dt class="text-muted-foreground">Processed</dt>
                    <dd class="mt-1 font-medium">
                        {{ syncLog.processedStocks }} / {{ syncLog.totalStocks }}
                    </dd>
                </div>
                <div class="rounded-lg bg-muted/40 p-3">
                    <dt class="text-muted-foreground">Successful</dt>
                    <dd class="mt-1 font-medium">
                        {{ syncLog.totalSynced }}
                    </dd>
                </div>
            </dl>

            <div class="space-y-2">
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <span>Progress</span>
                    <span>{{ progress(syncLog) }}%</span>
                </div>
                <div class="h-2 rounded-full bg-muted">
                    <div
                        class="h-2 rounded-full bg-primary transition-all"
                        :style="{ width: `${progress(syncLog)}%` }"
                    />
                </div>
            </div>

            <div v-if="syncLog.errorSummary" class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                {{ syncLog.errorSummary }}
            </div>
        </CardContent>
        <CardContent v-else class="py-10 text-center text-sm text-muted-foreground">
            No sync activity recorded yet.
        </CardContent>
    </Card>
</template>
