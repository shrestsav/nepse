<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, Play } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';
import BacktestRunPanel from '@/components/nepse/BacktestRunPanel.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import type { BacktestRunSummary, BacktestStrategyOption, BreadcrumbItem } from '@/types';

const props = defineProps<{
    currentRun: BacktestRunSummary | null;
    latestCompletedRun: BacktestRunSummary | null;
    recentRuns: BacktestRunSummary[];
    strategies: BacktestStrategyOption[];
    defaults: {
        startDate: string | null;
        endDate: string | null;
    };
    dateBounds: {
        min: string | null;
        max: string | null;
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
];

const selectedStrategy = ref(props.strategies[0]?.value ?? 'rsi_adx');
const startDate = ref(props.defaults.startDate ?? '');
const endDate = ref(props.defaults.endDate ?? '');
const page = usePage();

let pollingTimer: number | null = null;

function startPolling() {
    if (pollingTimer !== null) {
        return;
    }

    pollingTimer = window.setInterval(() => {
        router.reload({
            only: ['currentRun', 'latestCompletedRun', 'recentRuns'],
        });
    }, 3000);
}

function stopPolling() {
    if (pollingTimer !== null) {
        window.clearInterval(pollingTimer);
        pollingTimer = null;
    }
}

function submit() {
    router.post('/dashboard/backtesting', {
        strategy: selectedStrategy.value,
        start_date: startDate.value,
        end_date: endDate.value,
    }, {
        preserveScroll: true,
    });
}

watch(
    () => props.currentRun?.isRunning ?? false,
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
    <Head title="Backtesting" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <Alert v-if="page.props.flash.success">
                <CheckCircle2 class="size-4" />
                <AlertTitle>Backtest queued</AlertTitle>
                <AlertDescription>{{ page.props.flash.success }}</AlertDescription>
            </Alert>

            <Alert v-if="page.props.flash.error" variant="destructive">
                <AlertCircle class="size-4" />
                <AlertTitle>Backtest unavailable</AlertTitle>
                <AlertDescription>{{ page.props.flash.error }}</AlertDescription>
            </Alert>

            <Card class="border-border/60">
                <CardHeader class="space-y-1">
                    <CardTitle>Start a strategy replay</CardTitle>
                    <CardDescription>
                        Run the imported legacy backtest profiles against the stored NEPSE price-history dataset.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 lg:grid-cols-3">
                        <div class="space-y-2">
                            <Label for="strategy">Strategy</Label>
                            <select
                                id="strategy"
                                v-model="selectedStrategy"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option
                                    v-for="strategy in props.strategies"
                                    :key="strategy.value"
                                    :value="strategy.value"
                                >
                                    {{ strategy.label }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <Label for="start-date">Start date</Label>
                            <Input
                                id="start-date"
                                v-model="startDate"
                                type="date"
                                :min="props.dateBounds.min ?? undefined"
                                :max="props.dateBounds.max ?? undefined"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="end-date">End date</Label>
                            <Input
                                id="end-date"
                                v-model="endDate"
                                type="date"
                                :min="props.dateBounds.min ?? undefined"
                                :max="props.dateBounds.max ?? undefined"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <Button
                            :disabled="props.currentRun?.isRunning"
                            @click="submit"
                        >
                            <Play class="size-4" />
                            Start backtest
                        </Button>
                        <p class="text-sm text-muted-foreground">
                            {{ props.currentRun?.isRunning ? 'Wait for the active run to finish before starting another.' : 'Queued backtests run on the Laravel queue and write persisted trades/results.' }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <section class="grid gap-4 xl:grid-cols-2">
                <BacktestRunPanel
                    title="Current run"
                    description="This panel polls while a backtest is queued or running."
                    :run="props.currentRun"
                />
                <BacktestRunPanel
                    title="Latest completed run"
                    description="Most recent finished strategy replay."
                    :run="props.latestCompletedRun"
                />
            </section>

            <Card class="border-border/60">
                <CardHeader class="space-y-1">
                    <CardTitle>Recent runs</CardTitle>
                    <CardDescription>
                        Open a run to inspect its trade log and per-trade results.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="props.recentRuns.length > 0" class="space-y-3">
                        <div
                            v-for="run in props.recentRuns"
                            :key="run.id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 p-4"
                        >
                            <div class="space-y-1">
                                <p class="font-medium">
                                    {{ run.strategyLabel }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ run.startDate }} to {{ run.endDate }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="text-sm text-muted-foreground">
                                    {{ run.statusLabel }}
                                </span>
                                <span class="text-sm text-muted-foreground">
                                    {{ run.totalTrades }} trades
                                </span>
                                <Button variant="outline" as-child>
                                    <Link :href="`/dashboard/backtesting/${run.id}`">
                                        Open
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-10 text-center text-sm text-muted-foreground">
                        No backtest runs have been created yet.
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
