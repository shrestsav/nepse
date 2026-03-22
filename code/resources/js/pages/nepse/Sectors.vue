<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
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
import type { BreadcrumbItem, SectorOption } from '@/types';

const props = defineProps<{
    sectors: SectorOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Sectors',
        href: '/dashboard/sectors',
    },
];
</script>

<template>
    <Head title="Sectors" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="space-y-1">
                <h1 class="text-3xl font-semibold tracking-tight">
                    Market sectors
                </h1>
                <p class="text-sm text-muted-foreground">
                    Open any sector to jump straight into the filtered stock list.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card
                    v-for="sector in props.sectors"
                    :key="sector.id"
                    class="border-border/60"
                >
                    <CardHeader class="space-y-2">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <CardTitle>{{ sector.name }}</CardTitle>
                                <CardDescription>
                                    Sector-specific stock coverage view.
                                </CardDescription>
                            </div>
                            <Badge variant="secondary">
                                {{ sector.stockCount }}
                            </Badge>
                        </div>
                    </CardHeader>

                    <CardContent class="flex items-center justify-between gap-3">
                        <p class="text-sm text-muted-foreground">
                            {{ sector.stockCount }} tracked stock{{ sector.stockCount === 1 ? '' : 's' }}
                        </p>
                        <Button variant="outline" as-child>
                            <Link :href="dashboardRoutes.stocks({ query: { sector: sector.id } }).url">
                                Open
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
