<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import strategyRoutes from '@/routes/dashboard/strategies';
import type {
    BreadcrumbItem,
    StrategyCandidateRow,
    StrategyDetail,
    StrategyShowFilters,
    StrategyShowSummary,
} from '@/types';

const props = defineProps<{
    strategy: StrategyDetail;
    selectedDate: string | null;
    dateBounds: {
        min: string | null;
        max: string | null;
    };
    filters: StrategyShowFilters;
    summary: StrategyShowSummary;
    rows: StrategyCandidateRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Strategies',
        href: dashboardRoutes.strategies(),
    },
    {
        title: props.strategy.name,
        href: strategyRoutes.show(props.strategy.slug),
    },
];

const dateInput = ref(props.filters.date ?? props.selectedDate ?? '');
const minTurnoverInput = ref(String(props.filters.minTurnover));
const limitInput = ref(String(props.filters.limit));

watch(
    () => props.filters,
    (filters) => {
        dateInput.value = filters.date ?? props.selectedDate ?? '';
        minTurnoverInput.value = String(filters.minTurnover);
        limitInput.value = String(filters.limit);
    },
    { deep: true },
);

function applyFilters() {
    const parsedMinTurnover = Number(minTurnoverInput.value);
    const parsedLimit = Number(limitInput.value);

    router.get(strategyRoutes.show(props.strategy.slug).url, {
        date: dateInput.value || undefined,
        minTurnover: Number.isFinite(parsedMinTurnover) ? parsedMinTurnover : undefined,
        limit: Number.isFinite(parsedLimit) ? parsedLimit : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function formatAmount(value: number): string {
    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: 2,
    }).format(value);
}

function formatRatio(value: number): string {
    return `${(value * 100).toFixed(2)}%`;
}

function signalBadgeClass(signal: StrategyCandidateRow['signal']): string {
    if (signal === 'buy') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300';
    }

    if (signal === 'sell') {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300';
    }

    return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
}

function brokerList(entries: StrategyCandidateRow['topBuyers']): string {
    if (entries.length === 0) {
        return 'N/A';
    }

    return entries
        .map((entry) => `${entry.brokerNo} (${formatAmount(entry.amount)})`)
        .join(', ');
}
</script>

<template>
    <Head :title="strategy.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="space-y-1">
                <h1 class="text-3xl font-semibold tracking-tight">
                    {{ strategy.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ strategy.summary }}
                </p>
            </div>

            <Card class="border-border/60">
                <CardHeader class="space-y-1">
                    <CardTitle>Thesis</CardTitle>
                    <CardDescription>{{ strategy.thesis }}</CardDescription>
                </CardHeader>
            </Card>

            <div class="grid gap-4 xl:grid-cols-2">
                <Card class="border-border/60">
                    <CardHeader>
                        <CardTitle>How it is computed</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="list-disc space-y-2 pl-4 text-sm text-muted-foreground">
                            <li v-for="line in strategy.howComputed" :key="line">
                                {{ line }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card class="border-border/60">
                    <CardHeader>
                        <CardTitle>Entry / exit logic</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="list-disc space-y-2 pl-4 text-sm text-muted-foreground">
                            <li v-for="line in strategy.entryRules" :key="line">
                                {{ line }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <Card class="border-border/60">
                    <CardHeader>
                        <CardTitle>Risk controls</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="list-disc space-y-2 pl-4 text-sm text-muted-foreground">
                            <li v-for="line in strategy.riskControls" :key="line">
                                {{ line }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card class="border-border/60">
                    <CardHeader>
                        <CardTitle>Backtest plan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="list-disc space-y-2 pl-4 text-sm text-muted-foreground">
                            <li v-for="line in strategy.backtestPlan" :key="line">
                                {{ line }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-border/60">
                <CardHeader>
                    <CardTitle>Filters</CardTitle>
                    <CardDescription>Adjust thresholds and rerun the strategy calculation for the selected date.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="grid gap-3 md:grid-cols-4">
                        <div class="space-y-2">
                            <Label for="strategy-date">Trade date</Label>
                            <Input
                                id="strategy-date"
                                v-model="dateInput"
                                type="date"
                                :min="props.dateBounds.min ?? undefined"
                                :max="props.dateBounds.max ?? undefined"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="strategy-turnover">Minimum turnover</Label>
                            <Input
                                id="strategy-turnover"
                                v-model="minTurnoverInput"
                                type="number"
                                min="0"
                                step="1000"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="strategy-limit">Result limit</Label>
                            <select
                                id="strategy-limit"
                                v-model="limitInput"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <Button @click="applyFilters">
                                Apply
                            </Button>
                        </div>
                    </div>

                    <p
                        v-if="filters.date && selectedDate && filters.date !== selectedDate"
                        class="text-sm text-muted-foreground"
                    >
                        No exact data existed for {{ filters.date }}. Showing the nearest previous trade date {{ selectedDate }}.
                    </p>
                </CardContent>
            </Card>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <Card class="border-border/60">
                    <CardHeader class="pb-2">
                        <CardDescription>Symbols scanned</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.symbolsScanned }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card class="border-border/60">
                    <CardHeader class="pb-2">
                        <CardDescription>Passing turnover</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.symbolsPassingTurnover }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card class="border-border/60">
                    <CardHeader class="pb-2">
                        <CardDescription>Buy candidates</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.buyCandidates }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card class="border-border/60">
                    <CardHeader class="pb-2">
                        <CardDescription>Sell candidates</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.sellCandidates }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card class="border-border/60">
                    <CardHeader class="pb-2">
                        <CardDescription>Neutral</CardDescription>
                        <CardTitle class="text-2xl">{{ summary.neutral }}</CardTitle>
                    </CardHeader>
                </Card>
            </section>

            <Card class="border-border/60">
                <CardHeader>
                    <CardTitle>Candidate table</CardTitle>
                    <CardDescription>
                        Ranked by |netFlowRatio| descending, then turnover.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="rows.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        No symbols matched the filters for the selected date.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-border/60 text-left text-muted-foreground">
                                    <th class="px-2 py-2">Symbol</th>
                                    <th class="px-2 py-2">Signal</th>
                                    <th class="px-2 py-2">Close</th>
                                    <th class="px-2 py-2">Change %</th>
                                    <th class="px-2 py-2">Turnover</th>
                                    <th class="px-2 py-2">Top-5 Net</th>
                                    <th class="px-2 py-2">Net-flow ratio</th>
                                    <th class="px-2 py-2">Dominance ratio</th>
                                    <th class="px-2 py-2">Buyer brokers</th>
                                    <th class="px-2 py-2">Seller brokers</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in rows"
                                    :key="row.symbol"
                                    class="border-b border-border/40 align-top"
                                >
                                    <td class="px-2 py-2 font-medium">{{ row.symbol }}</td>
                                    <td class="px-2 py-2">
                                        <Badge :class="signalBadgeClass(row.signal)">
                                            {{ row.signal.toUpperCase() }}
                                        </Badge>
                                    </td>
                                    <td class="px-2 py-2">{{ row.close !== null ? formatAmount(row.close) : 'N/A' }}</td>
                                    <td class="px-2 py-2">{{ row.changePercent !== null ? `${row.changePercent.toFixed(2)}%` : 'N/A' }}</td>
                                    <td class="px-2 py-2">{{ formatAmount(row.turnover) }}</td>
                                    <td class="px-2 py-2">{{ formatAmount(row.netFlowTop5) }}</td>
                                    <td class="px-2 py-2">{{ formatRatio(row.netFlowRatio) }}</td>
                                    <td class="px-2 py-2">{{ formatRatio(row.dominanceRatio) }}</td>
                                    <td class="px-2 py-2">{{ row.buyerBrokers }}</td>
                                    <td class="px-2 py-2">{{ row.sellerBrokers }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="rows.length > 0" class="border-border/60">
                <CardHeader>
                    <CardTitle>Top broker flow per symbol</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-for="row in rows"
                        :key="`brokers-${row.symbol}`"
                        class="rounded-xl border border-border/60 p-4"
                    >
                        <p class="font-medium">{{ row.symbol }}</p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Top buyers: {{ brokerList(row.topBuyers) }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Top sellers: {{ brokerList(row.topSellers) }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
