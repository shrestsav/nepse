<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, Search, TableProperties, X } from 'lucide-vue-next';
import { computed, ref, toRefs } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type {
    BreadcrumbItem,
    BrokerOption,
    FloorsheetFilters,
    FloorsheetRow,
    Paginated,
} from '@/types';

const props = defineProps<{
    floorsheets: Paginated<FloorsheetRow>;
    brokers: BrokerOption[];
    filters: FloorsheetFilters;
    dateBounds: {
        min: string | null;
        max: string | null;
    };
    summary: {
        matchingRows: number;
        shownRows: number;
    };
}>();

const { brokers, dateBounds, filters, floorsheets, summary } = toRefs(props);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Floorsheet',
        href: '/dashboard/floorsheet',
    },
];

const date = ref(props.filters.date);
const symbol = ref(props.filters.symbol ?? '');
const buyer = ref(props.filters.buyer ?? 'all');
const seller = ref(props.filters.seller ?? 'all');
const quantityRange = ref<FloorsheetFilters['quantityRange']>(props.filters.quantityRange);

const quantityOptions: Array<{ label: string; value: FloorsheetFilters['quantityRange'] }> = [
    { label: 'All', value: 'all' },
    { label: '0-10', value: '0-10' },
    { label: '10-100', value: '10-100' },
    { label: '100-1K', value: '100-1k' },
];

const activeBuyer = computed(() => {
    if (buyer.value === 'all') {
        return null;
    }

    return props.brokers.find((option) => option.brokerNo === buyer.value) ?? null;
});

const activeSeller = computed(() => {
    if (seller.value === 'all') {
        return null;
    }

    return props.brokers.find((option) => option.brokerNo === seller.value) ?? null;
});

function applyFilters(overrides: Partial<Record<'date' | 'symbol' | 'buyer' | 'seller' | 'quantityRange' | 'page', string | number | undefined>> = {}) {
    router.get(
        '/dashboard/floorsheet',
        {
            date: overrides.date ?? date.value,
            symbol: overrides.symbol ?? (symbol.value.trim() || undefined),
            buyer: overrides.buyer ?? (buyer.value === 'all' ? undefined : buyer.value),
            seller: overrides.seller ?? (seller.value === 'all' ? undefined : seller.value),
            quantityRange: overrides.quantityRange ?? quantityRange.value,
            page: overrides.page,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function clearFilters() {
    symbol.value = '';
    buyer.value = 'all';
    seller.value = 'all';
    quantityRange.value = 'all';

    applyFilters({
        symbol: undefined,
        buyer: undefined,
        seller: undefined,
        quantityRange: 'all',
        page: undefined,
    });
}

function changeDate() {
    applyFilters({ page: undefined });
}

function chooseQuantityRange(value: FloorsheetFilters['quantityRange']) {
    quantityRange.value = value;
    applyFilters({ quantityRange: value, page: undefined });
}

function submitSearch() {
    applyFilters({ page: undefined });
}

function formatBrokerLabel(brokerNo: string, brokerName: string | null): string {
    return brokerName ? `${brokerNo} · ${brokerName}` : brokerNo;
}

function displayBrokerNo(brokerNo: string | null): string {
    return brokerNo ?? 'N/A';
}

function formatQuantity(quantity: number): string {
    return new Intl.NumberFormat().format(quantity);
}

function formatRate(rate: number): string {
    return Number(rate).toFixed(2);
}

function formatAmount(amount: number): string {
    if (amount >= 100000) {
        return `${(amount / 100000).toFixed(2)} Lac.`;
    }

    if (amount >= 1000) {
        return `${(amount / 1000).toFixed(2)} K`;
    }

    return amount.toFixed(2);
}
</script>

<template>
    <Head title="Floorsheet" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <TableProperties class="size-5" />
                    </div>
                    <div class="space-y-1">
                        <h1 class="text-3xl font-semibold tracking-tight">
                            Floorsheet
                        </h1>
                        <p class="text-sm font-medium text-primary">
                            {{ filters.date }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Showing {{ summary.shownRows }} of {{ summary.matchingRows }} trades for the selected market day.
                        </p>
                    </div>
                </div>

                <div class="w-full xl:max-w-xs">
                    <Label for="floorsheet-date" class="sr-only">Trade date</Label>
                    <div class="relative">
                        <CalendarDays class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-primary" />
                        <Input
                            id="floorsheet-date"
                            v-model="date"
                            type="date"
                            :min="dateBounds.min ?? undefined"
                            :max="dateBounds.max ?? undefined"
                            class="pr-10"
                            @change="changeDate"
                        />
                    </div>
                </div>
            </div>

            <Card class="border-border/60">
                <CardHeader class="space-y-4">
                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.25fr)_320px_320px_auto]">
                        <div class="space-y-2">
                            <Label for="floorsheet-symbol">Stock symbol</Label>
                            <div class="relative">
                                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="floorsheet-symbol"
                                    v-model="symbol"
                                    type="search"
                                    placeholder="Stock Symbol"
                                    class="pl-9"
                                    @keydown.enter.prevent="submitSearch"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="floorsheet-buyer">Buyer</Label>
                            <select
                                id="floorsheet-buyer"
                                v-model="buyer"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                @change="applyFilters({ page: undefined })"
                            >
                                <option value="all">
                                    Buyer
                                </option>
                                <option
                                    v-for="broker in brokers"
                                    :key="`buyer-${broker.brokerNo}`"
                                    :value="broker.brokerNo"
                                >
                                    {{ broker.brokerNo }} - {{ broker.brokerName }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label for="floorsheet-seller">Seller</Label>
                            <select
                                id="floorsheet-seller"
                                v-model="seller"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                @change="applyFilters({ page: undefined })"
                            >
                                <option value="all">
                                    Seller
                                </option>
                                <option
                                    v-for="broker in brokers"
                                    :key="`seller-${broker.brokerNo}`"
                                    :value="broker.brokerNo"
                                >
                                    {{ broker.brokerNo }} - {{ broker.brokerName }}
                                </option>
                            </select>
                        </div>

                        <div class="flex items-end gap-2">
                            <Button @click="submitSearch">
                                Apply
                            </Button>
                            <Button variant="outline" @click="clearFilters">
                                <X class="size-4" />
                                Clear
                            </Button>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge v-if="filters.symbol" variant="secondary">
                                Symbol: {{ filters.symbol }}
                            </Badge>
                            <Badge v-if="activeBuyer" variant="secondary">
                                Buyer: {{ formatBrokerLabel(activeBuyer.brokerNo, activeBuyer.brokerName) }}
                            </Badge>
                            <Badge v-if="activeSeller" variant="secondary">
                                Seller: {{ formatBrokerLabel(activeSeller.brokerNo, activeSeller.brokerName) }}
                            </Badge>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                v-for="option in quantityOptions"
                                :key="option.value"
                                :variant="quantityRange === option.value ? 'default' : 'outline'"
                                size="sm"
                                @click="chooseQuantityRange(option.value)"
                            >
                                {{ option.label }}
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div
                        v-if="floorsheets.data.length === 0"
                        class="rounded-xl border border-dashed border-border/60 px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        No floorsheet entries match the selected filters for {{ filters.date }}.
                    </div>

                    <div v-else class="space-y-4">
                        <div class="hidden overflow-x-auto rounded-xl border border-border/60 xl:block">
                            <table class="min-w-full text-sm">
                                <thead class="bg-muted/40 text-muted-foreground">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium uppercase tracking-wide">Transaction</th>
                                        <th class="px-4 py-3 text-center font-medium uppercase tracking-wide">Symbol</th>
                                        <th class="px-4 py-3 text-center font-medium uppercase tracking-wide">Buyer</th>
                                        <th class="px-4 py-3 text-center font-medium uppercase tracking-wide">Seller</th>
                                        <th class="px-4 py-3 text-center font-medium uppercase tracking-wide">Quantity</th>
                                        <th class="px-4 py-3 text-center font-medium uppercase tracking-wide">Rate</th>
                                        <th class="px-4 py-3 text-center font-medium uppercase tracking-wide">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60">
                                    <tr
                                        v-for="row in floorsheets.data"
                                        :key="row.id"
                                        class="transition hover:bg-muted/20"
                                    >
                                        <td class="px-4 py-3 font-medium">{{ row.transaction }}</td>
                                        <td class="px-4 py-3 text-center font-medium">{{ row.symbol }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="space-y-0.5">
                                                <p>{{ displayBrokerNo(row.buyerBrokerNo) }}</p>
                                                <p
                                                    v-if="row.buyerBrokerName"
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ row.buyerBrokerName }}
                                                </p>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="space-y-0.5">
                                                <p>{{ displayBrokerNo(row.sellerBrokerNo) }}</p>
                                                <p
                                                    v-if="row.sellerBrokerName"
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ row.sellerBrokerName }}
                                                </p>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">{{ formatQuantity(row.quantity) }}</td>
                                        <td class="px-4 py-3 text-center">{{ formatRate(row.rate) }}</td>
                                        <td class="px-4 py-3 text-center">{{ formatAmount(row.amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="grid gap-4 xl:hidden">
                            <Card
                                v-for="row in floorsheets.data"
                                :key="row.id"
                                class="border-border/60"
                            >
                                <CardContent class="flex flex-col gap-4 py-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="space-y-1">
                                            <p class="font-semibold">{{ row.symbol }}</p>
                                            <p class="text-sm text-muted-foreground">{{ row.transaction }}</p>
                                        </div>
                                        <Badge variant="outline">
                                            {{ formatQuantity(row.quantity) }}
                                        </Badge>
                                    </div>

                                    <div class="grid gap-3 text-sm sm:grid-cols-2">
                                        <div>
                                            <p class="text-muted-foreground">Buyer</p>
                                            <p class="font-medium">{{ displayBrokerNo(row.buyerBrokerNo) }}</p>
                                            <p
                                                v-if="row.buyerBrokerName"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ row.buyerBrokerName }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-muted-foreground">Seller</p>
                                            <p class="font-medium">{{ displayBrokerNo(row.sellerBrokerNo) }}</p>
                                            <p
                                                v-if="row.sellerBrokerName"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ row.sellerBrokerName }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-muted-foreground">Rate</p>
                                            <p class="font-medium">{{ formatRate(row.rate) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-muted-foreground">Amount</p>
                                            <p class="font-medium">{{ formatAmount(row.amount) }}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-border/60 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-muted-foreground">
                                Page {{ floorsheets.current_page }} of {{ floorsheets.last_page }}
                            </p>

                            <div class="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="!floorsheets.prev_page_url"
                                    as-child
                                >
                                    <Link
                                        v-if="floorsheets.prev_page_url"
                                        :href="floorsheets.prev_page_url"
                                        preserve-scroll
                                        preserve-state
                                    >
                                        Previous
                                    </Link>
                                    <span v-else>Previous</span>
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="!floorsheets.next_page_url"
                                    as-child
                                >
                                    <Link
                                        v-if="floorsheets.next_page_url"
                                        :href="floorsheets.next_page_url"
                                        preserve-scroll
                                        preserve-state
                                    >
                                        Next
                                    </Link>
                                    <span v-else>Next</span>
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
