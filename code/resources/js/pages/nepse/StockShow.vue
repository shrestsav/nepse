<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CalendarRange } from 'lucide-vue-next';
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
import type {
    BreadcrumbItem,
    StockDetailItem,
    StockPriceHistoryItem,
    StockPriceRangeSummary,
} from '@/types';

const props = defineProps<{
    stock: StockDetailItem;
    filters: {
        from: string | null;
        to: string | null;
    };
    dateBounds: {
        min: string | null;
        max: string | null;
    };
    rangeSummary: StockPriceRangeSummary | null;
    priceHistories: StockPriceHistoryItem[];
}>();

const fromDate = ref(props.filters.from ?? '');
const toDate = ref(props.filters.to ?? '');

watch(
    () => props.filters,
    (value) => {
        fromDate.value = value.from ?? '';
        toDate.value = value.to ?? ''
    },
    { deep: true },
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Stocks',
        href: dashboardRoutes.stocks(),
    },
    {
        title: props.stock.symbol,
        href: `/dashboard/stocks/${props.stock.id}`,
    },
];

function applyRange() {
    const query: Record<string, string> = {};

    if (fromDate.value) {
        query.from = fromDate.value;
    }

    if (toDate.value) {
        query.to = toDate.value;
    }

    router.get(`/dashboard/stocks/${props.stock.id}`, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearRange() {
    fromDate.value = '';
    toDate.value = '';
    applyRange();
}

function openNativePicker(event: Event) {
    const input = event.target as HTMLInputElement & {
        showPicker?: () => void;
    };

    input.showPicker?.();
}

function formatNumber(value: number | null, digits = 2) {
    return value === null ? 'N/A' : Number(value).toFixed(digits);
}

function formatInteger(value: number | null) {
    return value === null ? 'N/A' : Number(value).toLocaleString();
}
</script>

<template>
    <Head :title="`${props.stock.symbol} Price History`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-2">
                    <Button variant="ghost" size="sm" as-child class="w-fit px-0 text-muted-foreground">
                        <Link :href="dashboardRoutes.stocks()">
                            <ArrowLeft class="size-4" />
                            Back to stocks
                        </Link>
                    </Button>
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-3xl font-semibold tracking-tight">
                                {{ props.stock.symbol }}
                            </h1>
                            <Badge variant="outline">
                                {{ props.stock.sector ?? 'Unknown sector' }}
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ props.stock.companyName }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Badge v-if="props.dateBounds.min" variant="outline">
                        First stored date: {{ props.dateBounds.min }}
                    </Badge>
                    <Badge v-if="props.dateBounds.max" variant="outline">
                        Latest stored date: {{ props.dateBounds.max }}
                    </Badge>
                </div>
            </div>

            <Card class="border-border/60">
                <CardHeader class="space-y-1">
                    <CardTitle>Filter by date range</CardTitle>
                    <CardDescription>
                        Limit the price range summary and the latest 100 history rows to a specific period.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto]">
                        <div class="space-y-2">
                            <Label for="from-date">From date</Label>
                            <div class="relative">
                                <CalendarRange class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="from-date"
                                    v-model="fromDate"
                                    type="date"
                                    :min="props.dateBounds.min ?? undefined"
                                    :max="props.dateBounds.max ?? undefined"
                                    class="cursor-pointer pl-9"
                                    @focus="openNativePicker"
                                    @click="openNativePicker"
                                />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label for="to-date">To date</Label>
                            <div class="relative">
                                <CalendarRange class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="to-date"
                                    v-model="toDate"
                                    type="date"
                                    :min="props.dateBounds.min ?? undefined"
                                    :max="props.dateBounds.max ?? undefined"
                                    class="cursor-pointer pl-9"
                                    @focus="openNativePicker"
                                    @click="openNativePicker"
                                />
                            </div>
                        </div>
                        <Button class="self-end" @click="applyRange">
                            Apply range
                        </Button>
                        <Button variant="outline" class="self-end" @click="clearRange">
                            Clear
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <section v-if="props.rangeSummary" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Card class="border-border/60">
                    <CardHeader class="space-y-1">
                        <CardTitle class="text-base">Price range</CardTitle>
                        <CardDescription>
                            {{ props.rangeSummary.firstDate }} to {{ props.rangeSummary.lastDate }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-1">
                        <p class="text-2xl font-semibold">
                            {{ formatNumber(props.rangeSummary.lowPrice) }} to {{ formatNumber(props.rangeSummary.highPrice) }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Low to high within the selected window.
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-border/60">
                    <CardHeader class="space-y-1">
                        <CardTitle class="text-base">Close movement</CardTitle>
                        <CardDescription>
                            Earliest close to latest close in range
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-1">
                        <p class="text-2xl font-semibold">
                            {{ formatNumber(props.rangeSummary.earliestClose) }} to {{ formatNumber(props.rangeSummary.latestClose) }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ formatNumber(props.rangeSummary.closeChange) }} ({{ formatNumber(props.rangeSummary.closeChangePercent) }}%)
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-border/60">
                    <CardHeader class="space-y-1">
                        <CardTitle class="text-base">Matching records</CardTitle>
                        <CardDescription>
                            Total rows inside the active filter
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-1">
                        <p class="text-2xl font-semibold">
                            {{ formatInteger(props.rangeSummary.matchingRecords) }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Showing {{ formatInteger(props.rangeSummary.shownRecords) }} row(s) below.
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-border/60">
                    <CardHeader class="space-y-1">
                        <CardTitle class="text-base">Window coverage</CardTitle>
                        <CardDescription>
                            Stored trading dates in the selected range
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-1 text-sm">
                        <p><span class="text-muted-foreground">First:</span> {{ props.rangeSummary.firstDate }}</p>
                        <p><span class="text-muted-foreground">Last:</span> {{ props.rangeSummary.lastDate }}</p>
                    </CardContent>
                </Card>
            </section>

            <Card class="border-border/60">
                <CardHeader class="space-y-1">
                    <CardTitle>Price history</CardTitle>
                    <CardDescription>
                        Latest 100 records, always sorted newest first.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="props.priceHistories.length > 0" class="space-y-4">
                        <p
                            v-if="props.rangeSummary && props.rangeSummary.matchingRecords > props.rangeSummary.shownRecords"
                            class="text-sm text-muted-foreground"
                        >
                            More than 100 rows match this range. Only the most recent 100 are shown here.
                        </p>

                        <div class="hidden overflow-x-auto lg:block">
                            <table class="min-w-full divide-y divide-border text-sm">
                                <thead>
                                    <tr class="text-left text-muted-foreground">
                                        <th class="py-3 pr-4 font-medium">Date</th>
                                        <th class="py-3 pr-4 font-medium">Close</th>
                                        <th class="py-3 pr-4 font-medium">High</th>
                                        <th class="py-3 pr-4 font-medium">Low</th>
                                        <th class="py-3 pr-4 font-medium">Change</th>
                                        <th class="py-3 pr-4 font-medium">Change %</th>
                                        <th class="py-3 pr-4 font-medium">Volume</th>
                                        <th class="py-3 pr-4 font-medium">Transactions</th>
                                        <th class="py-3 font-medium">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60">
                                    <tr v-for="history in props.priceHistories" :key="history.id">
                                        <td class="py-3 pr-4 font-medium">{{ history.date ?? 'N/A' }}</td>
                                        <td class="py-3 pr-4">{{ formatNumber(history.close) }}</td>
                                        <td class="py-3 pr-4">{{ formatNumber(history.high) }}</td>
                                        <td class="py-3 pr-4">{{ formatNumber(history.low) }}</td>
                                        <td class="py-3 pr-4">{{ formatNumber(history.change) }}</td>
                                        <td class="py-3 pr-4">{{ formatNumber(history.changePercent) }}</td>
                                        <td class="py-3 pr-4">{{ formatInteger(history.volume) }}</td>
                                        <td class="py-3 pr-4">{{ formatInteger(history.transactions) }}</td>
                                        <td class="py-3">{{ formatNumber(history.amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="grid gap-4 lg:hidden">
                            <Card
                                v-for="history in props.priceHistories"
                                :key="history.id"
                                class="border-border/60"
                            >
                                <CardContent class="grid gap-3 py-5">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="font-semibold">{{ history.date ?? 'N/A' }}</p>
                                        <Badge variant="outline">
                                            {{ formatNumber(history.changePercent) }}%
                                        </Badge>
                                    </div>
                                    <div class="grid gap-3 text-sm sm:grid-cols-2">
                                        <div>
                                            <p class="text-muted-foreground">Close</p>
                                            <p class="font-medium">{{ formatNumber(history.close) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-muted-foreground">High / Low</p>
                                            <p class="font-medium">
                                                {{ formatNumber(history.high) }} / {{ formatNumber(history.low) }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-muted-foreground">Volume</p>
                                            <p class="font-medium">{{ formatInteger(history.volume) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-muted-foreground">Transactions</p>
                                            <p class="font-medium">{{ formatInteger(history.transactions) }}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <div v-else class="py-10 text-center text-sm text-muted-foreground">
                        No price history matches the selected date range.
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
