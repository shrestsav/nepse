<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
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
import dashboardRoutes from '@/routes/dashboard';
import type { BreadcrumbItem, Paginated, StockIndexItem } from '@/types';

const props = defineProps<{
    stocks: Paginated<StockIndexItem>;
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
        title: 'Stocks',
        href: dashboardRoutes.stocks(),
    },
];

const kathmanduDateTimeFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Kathmandu',
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
                    Catalog and history coverage for the migrated NEPSE dataset.
                </p>
            </div>

            <Card class="border-border/60">
                <CardHeader class="space-y-1">
                    <CardTitle>Tracked symbols</CardTitle>
                    <CardDescription>
                        Page {{ props.stocks.current_page }} of {{ props.stocks.last_page }}.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="hidden overflow-x-auto lg:block">
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
                                <tr v-for="stock in props.stocks.data" :key="stock.id">
                                    <td class="py-3 pr-4 font-medium">
                                        <Link
                                            :href="`/dashboard/stocks/${stock.id}`"
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
                                    <td class="py-3">{{ stock.latestClose !== null ? Number(stock.latestClose).toFixed(2) : 'N/A' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid gap-4 lg:hidden">
                        <Card
                            v-for="stock in props.stocks.data"
                            :key="stock.id"
                            class="border-border/60"
                        >
                            <CardContent class="flex flex-col gap-3 py-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="space-y-1">
                                        <Link
                                            :href="`/dashboard/stocks/${stock.id}`"
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
                                        <Link :href="`/dashboard/stocks/${stock.id}`">
                                            View price history
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <Button
                            v-if="props.stocks.prev_page_url"
                            variant="outline"
                            as-child
                        >
                            <Link
                                :href="dashboardRoutes.stocks({ query: { page: props.stocks.current_page - 1 } })"
                                preserve-scroll
                            >
                                Previous
                            </Link>
                        </Button>
                        <Button v-else variant="outline" disabled>
                            Previous
                        </Button>

                        <p class="text-sm text-muted-foreground">
                            Showing {{ props.stocks.data.length }} of {{ props.stocks.total }} symbols
                        </p>

                        <Button
                            v-if="props.stocks.next_page_url"
                            variant="outline"
                            as-child
                        >
                            <Link
                                :href="dashboardRoutes.stocks({ query: { page: props.stocks.current_page + 1 } })"
                                preserve-scroll
                            >
                                Next
                            </Link>
                        </Button>
                        <Button v-else variant="outline" disabled>
                            Next
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
