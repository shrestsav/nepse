<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { RefreshCcw } from 'lucide-vue-next';
import { onBeforeUnmount, watch } from 'vue';
import BacktestRunPanel from '@/components/nepse/BacktestRunPanel.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BacktestRunSummary, BacktestTrade, BreadcrumbItem, Paginated } from '@/types';

const props = defineProps<{
    run: BacktestRunSummary | null;
    trades: Paginated<BacktestTrade>;
    filters: {
        page: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Backtesting',
        href: '/dashboard/backtesting',
    },
    {
        title: props.run?.strategyLabel ?? 'Run detail',
        href: props.run ? `/dashboard/backtesting/${props.run.id}` : '/dashboard/backtesting',
    },
];

let pollingTimer: number | null = null;

function startPolling() {
    if (pollingTimer !== null || !props.run) {
        return;
    }

    pollingTimer = window.setInterval(() => {
        router.reload({
            only: ['run', 'trades'],
        });
    }, 3000);
}

function stopPolling() {
    if (pollingTimer !== null) {
        window.clearInterval(pollingTimer);
        pollingTimer = null;
    }
}

watch(
    () => props.run?.isRunning ?? false,
    (isRunning) => {
        if (isRunning) {
            startPolling();

            return;
        }

        stopPolling();
    },
    { immediate: true },
);

onBeforeUnmount(() => stopPolling());
</script>

<template>
    <Head :title="props.run ? `${props.run.strategyLabel} backtest` : 'Backtest detail'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <h1 class="text-3xl font-semibold tracking-tight">
                        {{ props.run?.strategyLabel ?? 'Backtest detail' }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Review the persisted trade log, exit reasons, and run summary for this strategy replay.
                    </p>
                </div>

                <Button variant="outline" as-child>
                    <Link href="/dashboard/backtesting">
                        <RefreshCcw class="size-4" />
                        All runs
                    </Link>
                </Button>
            </div>

            <BacktestRunPanel
                title="Run summary"
                description="Aggregated performance for the selected backtest run."
                :run="props.run"
            />

            <Card class="border-border/60">
                <CardHeader class="space-y-1">
                    <CardTitle>Trade log</CardTitle>
                    <CardDescription>
                        One row per completed trade created during the replay window.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="props.trades.data.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border text-sm">
                            <thead>
                                <tr class="text-left text-muted-foreground">
                                    <th class="py-3 pr-4">Symbol</th>
                                    <th class="py-3 pr-4">Buy</th>
                                    <th class="py-3 pr-4">Sell</th>
                                    <th class="py-3 pr-4">Stop</th>
                                    <th class="py-3 pr-4">Return</th>
                                    <th class="py-3 pr-4">Days</th>
                                    <th class="py-3 pr-4">Exit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="trade in props.trades.data"
                                    :key="trade.id"
                                    class="align-top"
                                >
                                    <td class="py-3 pr-4">
                                        <div class="font-medium">{{ trade.symbol }}</div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ trade.companyName ?? 'Unknown company' }}
                                        </div>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <div>{{ trade.buyDate }}</div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ trade.buyPrice.toFixed(2) }}
                                        </div>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <div>{{ trade.sellDate }}</div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ trade.sellPrice.toFixed(2) }}
                                        </div>
                                    </td>
                                    <td class="py-3 pr-4">
                                        {{ trade.stopLoss !== null ? trade.stopLoss.toFixed(2) : 'N/A' }}
                                    </td>
                                    <td class="py-3 pr-4">
                                        <span :class="trade.percentageReturn >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                            {{ trade.percentageReturn.toFixed(2) }}%
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4">
                                        {{ trade.holdingDays }}
                                    </td>
                                    <td class="py-3 pr-4">
                                        {{ trade.exitReason.replace('_', ' ') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="py-10 text-center text-sm text-muted-foreground">
                        No trades were created for this run.
                    </div>

                    <div class="flex items-center justify-between">
                        <Button
                            variant="outline"
                            :disabled="!props.trades.prev_page_url"
                            as-child
                        >
                            <Link :href="props.trades.prev_page_url ?? '#'" preserve-scroll>
                                Previous
                            </Link>
                        </Button>
                        <p class="text-sm text-muted-foreground">
                            Page {{ props.trades.current_page }} of {{ props.trades.last_page }}
                        </p>
                        <Button
                            variant="outline"
                            :disabled="!props.trades.next_page_url"
                            as-child
                        >
                            <Link :href="props.trades.next_page_url ?? '#'" preserve-scroll>
                                Next
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
