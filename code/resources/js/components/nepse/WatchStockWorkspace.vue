<script setup lang="ts">
import { AlertCircle, PauseCircle, PlayCircle, RadioTower } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import watchStockRoutes from '@/routes/dashboard/watch-stock';
import type { WatchStockOption, WatchStockQuote } from '@/types';

type WatchPoint = {
    price: number;
    recordedAt: string;
};

const props = withDefaults(
    defineProps<{
        stocks: WatchStockOption[];
        pollIntervalMs?: number;
        maxPoints?: number;
    }>(),
    {
        pollIntervalMs: 5000,
        maxPoints: 60,
    },
);

const selectedStockId = ref('');
const quote = ref<WatchStockQuote | null>(null);
const error = ref<string | null>(null);
const isWatching = ref(false);
const isLoading = ref(false);
const sessionPoints = ref<WatchPoint[]>([]);

let pollingTimer: number | null = null;

const currencyFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 2,
    minimumFractionDigits: 2,
});

const integerFormatter = new Intl.NumberFormat();

const kathmanduDateTimeFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Kathmandu',
});

const selectedStock = computed(() => {
    return props.stocks.find((stock) => String(stock.id) === selectedStockId.value) ?? null;
});

const sessionStartPrice = computed(() => sessionPoints.value[0]?.price ?? null);
const sessionCurrentPrice = computed(() => sessionPoints.value.at(-1)?.price ?? null);
const sessionHighPrice = computed(() => {
    if (sessionPoints.value.length === 0) {
        return null;
    }

    return Math.max(...sessionPoints.value.map((point) => point.price));
});
const sessionLowPrice = computed(() => {
    if (sessionPoints.value.length === 0) {
        return null;
    }

    return Math.min(...sessionPoints.value.map((point) => point.price));
});
const sessionChange = computed(() => {
    if (sessionStartPrice.value === null || sessionCurrentPrice.value === null) {
        return null;
    }

    return Number((sessionCurrentPrice.value - sessionStartPrice.value).toFixed(2));
});
const sessionChangePercent = computed(() => {
    if (sessionStartPrice.value === null || sessionCurrentPrice.value === null || sessionStartPrice.value === 0) {
        return null;
    }

    return Number((((sessionCurrentPrice.value - sessionStartPrice.value) / sessionStartPrice.value) * 100).toFixed(2));
});
const sessionDirection = computed<'up' | 'down' | 'flat'>(() => {
    if (sessionChange.value === null || sessionChange.value === 0) {
        return 'flat';
    }

    return sessionChange.value > 0 ? 'up' : 'down';
});
const sessionPointCount = computed(() => sessionPoints.value.length);
const chartPath = computed(() => {
    if (sessionPoints.value.length < 2) {
        return '';
    }

    const width = 100;
    const height = 100;
    const prices = sessionPoints.value.map((point) => point.price);
    const minPrice = Math.min(...prices);
    const maxPrice = Math.max(...prices);
    const range = maxPrice - minPrice;

    return sessionPoints.value
        .map((point, index) => {
            const x = sessionPoints.value.length === 1 ? width / 2 : (index / (sessionPoints.value.length - 1)) * width;
            const normalizedY = range === 0 ? 0.5 : (point.price - minPrice) / range;
            const y = height - (normalizedY * height);

            return `${index === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`;
        })
        .join(' ');
});

function formatCurrency(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return currencyFormatter.format(value);
}

function formatInteger(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return integerFormatter.format(value);
}

function formatSignedNumber(value: number | null, suffix = ''): string {
    if (value === null) {
        return '—';
    }

    const normalized = currencyFormatter.format(Math.abs(value));
    const prefix = value > 0 ? '+' : value < 0 ? '-' : '';

    return `${prefix}${normalized}${suffix}`;
}

function formatDateTime(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return kathmanduDateTimeFormatter.format(new Date(value));
}

function resetSession() {
    quote.value = null;
    error.value = null;
    sessionPoints.value = [];
}

function stopPolling() {
    if (pollingTimer !== null) {
        window.clearInterval(pollingTimer);
        pollingTimer = null;
    }
}

function stopWatching() {
    isWatching.value = false;
    stopPolling();
}

function appendPoint(nextQuote: WatchStockQuote) {
    sessionPoints.value = [
        ...sessionPoints.value,
        {
            price: nextQuote.price,
            recordedAt: nextQuote.recordedAt,
        },
    ].slice(-props.maxPoints);
}

async function fetchQuote(): Promise<boolean> {
    if (selectedStock.value === null) {
        error.value = 'Select a tracked stock before starting the live watch.';

        return false;
    }

    isLoading.value = true;

    try {
        const response = await fetch(
            watchStockRoutes.quote.url({
                query: {
                    stock: selectedStock.value.id,
                },
            }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        const payload = (await response.json()) as { message?: string; quote?: WatchStockQuote };

        if (! response.ok || payload.quote === undefined) {
            throw new Error(payload.message ?? 'Unable to fetch the latest live quote.');
        }

        quote.value = payload.quote;
        appendPoint(payload.quote);
        error.value = null;

        return true;
    } catch (caughtError) {
        error.value = caughtError instanceof Error ? caughtError.message : 'Unable to fetch the latest live quote.';

        return false;
    } finally {
        isLoading.value = false;
    }
}

function startPolling() {
    stopPolling();

    pollingTimer = window.setInterval(() => {
        void fetchQuote();
    }, props.pollIntervalMs);
}

async function startWatching() {
    if (selectedStock.value === null) {
        error.value = 'Select a tracked stock before starting the live watch.';

        return;
    }

    stopWatching();
    resetSession();

    const didFetch = await fetchQuote();

    if (! didFetch) {
        return;
    }

    isWatching.value = true;
    startPolling();
}

watch(selectedStockId, () => {
    stopWatching();
    resetSession();
});

onBeforeUnmount(() => {
    stopWatching();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <Alert v-if="error" variant="destructive">
            <AlertCircle class="size-4" />
            <AlertTitle>Live watch error</AlertTitle>
            <AlertDescription>{{ error }}</AlertDescription>
        </Alert>

        <Card class="border-border/60">
            <CardHeader class="space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <CardTitle>Watch a stock live</CardTitle>
                        <CardDescription>
                            Poll the current market snapshot every 5 seconds and build a session chart in the browser.
                        </CardDescription>
                    </div>
                    <Badge
                        :variant="isWatching ? 'default' : 'secondary'"
                        class="gap-2"
                        data-testid="watch-status"
                    >
                        <RadioTower class="size-3.5" />
                        {{ isWatching ? 'Watching' : 'Idle' }}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                <div class="space-y-2">
                    <Label for="watch-stock-select">Tracked stock</Label>
                    <select
                        id="watch-stock-select"
                        v-model="selectedStockId"
                        data-testid="stock-select"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option value="">
                            Select a stock
                        </option>
                        <option
                            v-for="stock in props.stocks"
                            :key="stock.id"
                            :value="String(stock.id)"
                        >
                            {{ stock.symbol }} · {{ stock.companyName }}
                        </option>
                    </select>
                    <p class="text-sm text-muted-foreground">
                        {{ selectedStock ? `${selectedStock.symbol} · ${selectedStock.sector ?? 'Unknown sector'}` : 'Choose one tracked symbol to begin.' }}
                    </p>
                </div>

                <div class="flex items-end gap-2">
                    <Button
                        :disabled="isLoading || selectedStockId === ''"
                        data-testid="watch-button"
                        @click="startWatching"
                    >
                        <PlayCircle class="size-4" />
                        Watch
                    </Button>
                    <Button
                        variant="outline"
                        :disabled="! isWatching"
                        data-testid="stop-button"
                        @click="stopWatching"
                    >
                        <PauseCircle class="size-4" />
                        Stop
                    </Button>
                </div>
            </CardContent>
        </Card>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Card class="border-border/60">
                <CardHeader class="space-y-1">
                    <CardDescription>Current price</CardDescription>
                    <CardTitle data-testid="current-price">{{ formatCurrency(quote?.price ?? null) }}</CardTitle>
                </CardHeader>
                <CardContent class="pt-0 text-sm text-muted-foreground">
                    {{ quote?.companyName ?? 'Start watching to load a live quote.' }}
                </CardContent>
            </Card>

            <Card class="border-border/60">
                <CardHeader class="space-y-1">
                    <CardDescription>Change</CardDescription>
                    <CardTitle
                        :class="{
                            'text-emerald-600': (quote?.change ?? 0) > 0,
                            'text-rose-600': (quote?.change ?? 0) < 0,
                        }"
                        data-testid="price-change"
                    >
                        {{ formatSignedNumber(quote?.change ?? null) }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="pt-0 text-sm text-muted-foreground">
                    {{ formatSignedNumber(quote?.changePercent ?? null, '%') }} versus previous close
                </CardContent>
            </Card>

            <Card class="border-border/60">
                <CardHeader class="space-y-1">
                    <CardDescription>Session performance</CardDescription>
                    <CardTitle
                        :class="{
                            'text-emerald-600': sessionDirection === 'up',
                            'text-rose-600': sessionDirection === 'down',
                        }"
                    >
                        {{ formatSignedNumber(sessionChange, '') }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="pt-0 text-sm text-muted-foreground">
                    {{ formatSignedNumber(sessionChangePercent, '%') }} since this watch session started
                </CardContent>
            </Card>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
            <Card class="border-border/60">
                <CardHeader class="space-y-1">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <CardTitle>Live watch chart</CardTitle>
                            <CardDescription>
                                Last {{ props.maxPoints }} watch points for this browser session.
                            </CardDescription>
                        </div>
                        <Badge variant="secondary" data-testid="session-point-count">
                            {{ sessionPointCount }} point{{ sessionPointCount === 1 ? '' : 's' }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="sessionPoints.length === 0"
                        class="flex h-72 items-center justify-center rounded-xl border border-dashed border-border/60 text-sm text-muted-foreground"
                    >
                        No live points yet. Start watching a stock to build the chart.
                    </div>

                    <div v-else class="space-y-4">
                        <div class="h-72 rounded-xl border border-border/60 bg-muted/20 p-4">
                            <svg
                                viewBox="0 0 100 100"
                                class="size-full overflow-visible"
                                role="img"
                                aria-label="Live watch chart"
                                data-testid="watch-chart"
                            >
                                <defs>
                                    <linearGradient id="watch-stock-gradient" x1="0%" x2="0%" y1="0%" y2="100%">
                                        <stop
                                            offset="0%"
                                            :stop-color="sessionDirection === 'down' ? '#f43f5e' : '#22c55e'"
                                            stop-opacity="0.25"
                                        />
                                        <stop
                                            offset="100%"
                                            :stop-color="sessionDirection === 'down' ? '#f43f5e' : '#22c55e'"
                                            stop-opacity="0.05"
                                        />
                                    </linearGradient>
                                </defs>
                                <path
                                    v-if="chartPath"
                                    :d="`${chartPath} L 100 100 L 0 100 Z`"
                                    fill="url(#watch-stock-gradient)"
                                />
                                <path
                                    v-if="chartPath"
                                    :d="chartPath"
                                    fill="none"
                                    :stroke="sessionDirection === 'down' ? '#f43f5e' : '#22c55e'"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2.5"
                                />
                                <circle
                                    v-if="sessionPoints.length === 1"
                                    cx="50"
                                    cy="50"
                                    r="3"
                                    :fill="sessionDirection === 'down' ? '#f43f5e' : '#22c55e'"
                                />
                            </svg>
                        </div>

                        <div class="flex items-center justify-between gap-3 text-sm text-muted-foreground">
                            <span>Started {{ formatDateTime(sessionPoints[0]?.recordedAt ?? null) }}</span>
                            <span>Latest {{ formatDateTime(sessionPoints.at(-1)?.recordedAt ?? null) }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border/60">
                <CardHeader class="space-y-1">
                    <CardTitle>Session and market details</CardTitle>
                    <CardDescription>
                        Market-day values plus session-only watch stats.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="space-y-1 rounded-xl border border-border/60 p-4">
                        <p class="text-sm text-muted-foreground">Session start</p>
                        <p class="text-lg font-semibold">{{ formatCurrency(sessionStartPrice) }}</p>
                    </div>
                    <div class="space-y-1 rounded-xl border border-border/60 p-4">
                        <p class="text-sm text-muted-foreground">Session high / low</p>
                        <p class="text-lg font-semibold">
                            {{ formatCurrency(sessionHighPrice) }} / {{ formatCurrency(sessionLowPrice) }}
                        </p>
                    </div>
                    <div class="space-y-1 rounded-xl border border-border/60 p-4">
                        <p class="text-sm text-muted-foreground">Previous close / open</p>
                        <p class="text-lg font-semibold">
                            {{ formatCurrency(quote?.previousClose ?? null) }} / {{ formatCurrency(quote?.open ?? null) }}
                        </p>
                    </div>
                    <div class="space-y-1 rounded-xl border border-border/60 p-4">
                        <p class="text-sm text-muted-foreground">Day high / low</p>
                        <p class="text-lg font-semibold">
                            {{ formatCurrency(quote?.high ?? null) }} / {{ formatCurrency(quote?.low ?? null) }}
                        </p>
                    </div>
                    <div class="space-y-1 rounded-xl border border-border/60 p-4">
                        <p class="text-sm text-muted-foreground">Volume</p>
                        <p class="text-lg font-semibold">{{ formatInteger(quote?.volume ?? null) }}</p>
                    </div>
                    <div class="space-y-1 rounded-xl border border-border/60 p-4">
                        <p class="text-sm text-muted-foreground">Market date / last synced</p>
                        <p class="text-lg font-semibold">{{ quote?.marketDate ?? '—' }}</p>
                        <p class="text-sm text-muted-foreground">{{ formatDateTime(quote?.latestSyncedAt ?? null) }}</p>
                    </div>
                </CardContent>
            </Card>
        </section>
    </div>
</template>
