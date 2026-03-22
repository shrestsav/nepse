<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
import type { BreadcrumbItem, SectorOption, StockIndexItem } from '@/types';

const props = defineProps<{
    stocks: StockIndexItem[];
    sectors: SectorOption[];
    filters: {
        search: string | null;
        sector: number | null;
    };
    summary: {
        totalStocks: number;
        filteredStocks: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Stocks',
        href: dashboardRoutes.stocks(),
    },
];

const search = ref(props.filters.search ?? '');
const selectedSector = ref(props.filters.sector !== null ? String(props.filters.sector) : 'all');

const kathmanduDateTimeFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Kathmandu',
});

const activeSector = computed(() => {
    if (selectedSector.value === 'all') {
        return null;
    }

    return props.sectors.find((sector) => String(sector.id) === selectedSector.value) ?? null;
});

function formatLatestDate(stock: StockIndexItem): string {
    return stock.latestDate ?? 'Not synced';
}

function formatLatestSyncedAt(value: string | null): string {
    if (value === null) {
        return 'No sync timestamp';
    }

    return kathmanduDateTimeFormatter.format(new Date(value));
}

function applyFilters() {
    router.get(
        dashboardRoutes.stocks().url,
        {
            search: search.value.trim() || undefined,
            sector: selectedSector.value === 'all' ? undefined : Number(selectedSector.value),
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function clearFilters() {
    search.value = '';
    selectedSector.value = 'all';

    router.get(
        dashboardRoutes.stocks().url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}
</script>

<template>
    <Head title="Stocks" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="space-y-1">
                <h1 class="text-3xl font-semibold tracking-tight">
                    Stock coverage
                </h1>
                <p class="text-sm text-muted-foreground">
                    Browse every tracked symbol, then narrow the list by sector or company search.
                </p>
            </div>

            <Card class="border-border/60">
                <CardHeader class="space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <CardTitle>Tracked symbols</CardTitle>
                            <CardDescription>
                                Showing {{ props.summary.filteredStocks }} of {{ props.summary.totalStocks }} symbols.
                            </CardDescription>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge v-if="props.filters.search" variant="secondary">
                                Search: {{ props.filters.search }}
                            </Badge>
                            <Badge v-if="activeSector" variant="secondary">
                                Sector: {{ activeSector.name }}
                            </Badge>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px_auto]">
                        <div class="space-y-2">
                            <Label for="stock-search">Search symbols or companies</Label>
                            <div class="relative">
                                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="stock-search"
                                    v-model="search"
                                    type="search"
                                    placeholder="NABIL, hydropower, bank..."
                                    class="pl-9"
                                    @keydown.enter.prevent="applyFilters"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="sector-filter">Filter by sector</Label>
                            <select
                                id="sector-filter"
                                v-model="selectedSector"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="all">
                                    All sectors
                                </option>
                                <option
                                    v-for="sector in props.sectors"
                                    :key="sector.id"
                                    :value="String(sector.id)"
                                >
                                    {{ sector.name }} ({{ sector.stockCount }})
                                </option>
                            </select>
                        </div>

                        <div class="flex items-end gap-2">
                            <Button @click="applyFilters">
                                Apply filters
                            </Button>
                            <Button variant="outline" @click="clearFilters">
                                <X class="size-4" />
                                Clear
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div
                        v-if="props.stocks.length === 0"
                        class="rounded-xl border border-dashed border-border/60 px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        No stocks match the current filters.
                    </div>

                    <div v-else class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full divide-y divide-border text-sm">
                            <thead>
                                <tr class="text-left text-muted-foreground">
                                    <th class="py-3 pr-4 font-medium">Symbol</th>
                                    <th class="py-3 pr-4 font-medium">Company</th>
                                    <th class="py-3 pr-4 font-medium">Sector</th>
                                    <th class="py-3 pr-4 font-medium">History rows</th>
                                    <th class="py-3 pr-4 font-medium">Latest date</th>
                                    <th class="py-3 font-medium">Latest close</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="stock in props.stocks" :key="stock.id">
                                    <td class="py-3 pr-4 font-medium">
                                        <Link
                                            :href="dashboardRoutes.stocks.show(stock.id).url"
                                            class="text-primary transition hover:text-primary/80 hover:underline"
                                        >
                                            {{ stock.symbol }}
                                        </Link>
                                    </td>
                                    <td class="py-3 pr-4">{{ stock.companyName }}</td>
                                    <td class="py-3 pr-4">{{ stock.sector ?? 'Unknown' }}</td>
                                    <td class="py-3 pr-4">{{ stock.priceHistoryCount }}</td>
                                    <td class="py-3 pr-4">
                                        <div class="space-y-1">
                                            <p>{{ formatLatestDate(stock) }}</p>
                                            <p
                                                v-if="stock.latestSyncedAt"
                                                class="text-xs text-muted-foreground"
                                            >
                                                Synced {{ formatLatestSyncedAt(stock.latestSyncedAt) }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        {{ stock.latestClose !== null ? Number(stock.latestClose).toFixed(2) : 'N/A' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid gap-4 lg:hidden">
                        <Card
                            v-for="stock in props.stocks"
                            :key="stock.id"
                            class="border-border/60"
                        >
                            <CardContent class="flex flex-col gap-3 py-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="space-y-1">
                                        <Link
                                            :href="dashboardRoutes.stocks.show(stock.id).url"
                                            class="font-semibold text-primary transition hover:text-primary/80 hover:underline"
                                        >
                                            {{ stock.symbol }}
                                        </Link>
                                        <p class="text-sm text-muted-foreground">
                                            {{ stock.companyName }}
                                        </p>
                                    </div>
                                    <Badge variant="outline">
                                        {{ stock.sector ?? 'Unknown' }}
                                    </Badge>
                                </div>

                                <div class="grid gap-3 text-sm sm:grid-cols-3">
                                    <div>
                                        <p class="text-muted-foreground">History rows</p>
                                        <p class="font-medium">{{ stock.priceHistoryCount }}</p>
                                    </div>
                                    <div>
                                        <p class="text-muted-foreground">Latest date</p>
                                        <p class="font-medium">{{ formatLatestDate(stock) }}</p>
                                        <p
                                            v-if="stock.latestSyncedAt"
                                            class="text-xs text-muted-foreground"
                                        >
                                            Synced {{ formatLatestSyncedAt(stock.latestSyncedAt) }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-muted-foreground">Latest close</p>
                                        <p class="font-medium">
                                            {{ stock.latestClose !== null ? Number(stock.latestClose).toFixed(2) : 'N/A' }}
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <Button variant="outline" size="sm" as-child>
                                        <Link :href="dashboardRoutes.stocks.show(stock.id).url">
                                            View price history
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
