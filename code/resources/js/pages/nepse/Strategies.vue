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
import type { BreadcrumbItem, StrategyListItem } from '@/types';

const props = defineProps<{
    strategies: StrategyListItem[];
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
];
</script>

<template>
    <Head title="Strategies" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="space-y-1">
                <h1 class="text-3xl font-semibold tracking-tight">
                    Strategies
                </h1>
                <p class="text-sm text-muted-foreground">
                    Explore rule definitions and on-demand signal diagnostics for strategy experiments.
                </p>
            </div>

            <div
                v-if="props.strategies.length > 0"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <Card
                    v-for="strategy in props.strategies"
                    :key="strategy.slug"
                    class="border-border/60"
                >
                    <CardHeader class="space-y-2">
                        <Badge variant="outline" class="w-fit">
                            Strategy
                        </Badge>
                        <CardTitle>{{ strategy.name }}</CardTitle>
                        <CardDescription>
                            {{ strategy.summary }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button as-child>
                            <Link :href="strategy.url">
                                Open strategy
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <Card v-else class="border-border/60">
                <CardContent class="py-10 text-center text-sm text-muted-foreground">
                    No strategies are configured yet.
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
