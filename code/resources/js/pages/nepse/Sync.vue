<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, RefreshCcw } from 'lucide-vue-next';
import { onBeforeUnmount, watch } from 'vue';
import SyncLogPanel from '@/components/nepse/SyncLogPanel.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import syncRoutes from '@/routes/dashboard/sync';
import type { BreadcrumbItem, SyncLogSummary } from '@/types';

const props = defineProps<{
    currentSync: SyncLogSummary | null;
    latestSync: SyncLogSummary | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Sync',
        href: dashboardRoutes.sync(),
    },
];

const page = usePage();

let pollingTimer: number | null = null;

function startPolling() {
    if (pollingTimer !== null) {
        return;
    }

    pollingTimer = window.setInterval(() => {
        router.reload({
            only: ['currentSync', 'latestSync'],
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
    router.post(
        syncRoutes.store.url(),
        { mode: 'live' },
        {
            preserveScroll: true,
        },
    );
}

watch(
    () => props.currentSync?.isRunning ?? false,
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
    <Head title="Sync" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <Alert v-if="page.props.flash.success">
                <CheckCircle2 class="size-4" />
                <AlertTitle>Sync queued</AlertTitle>
                <AlertDescription>{{ page.props.flash.success }}</AlertDescription>
            </Alert>

            <Alert v-if="page.props.flash.error" variant="destructive">
                <AlertCircle class="size-4" />
                <AlertTitle>Sync unavailable</AlertTitle>
                <AlertDescription>{{ page.props.flash.error }}</AlertDescription>
            </Alert>

            <Card class="border-border/60">
                <CardHeader class="space-y-1">
                    <CardTitle>Start a live NEPSE sync</CardTitle>
                    <CardDescription>
                        Use the dashboard for live market refreshes only. Smart and full syncs are terminal-only.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="rounded-xl border border-primary/20 bg-primary/5 p-4">
                        <div class="space-y-2">
                            <p class="font-medium">Live</p>
                            <p class="text-sm text-muted-foreground">
                                Only refresh current-day prices for already tracked stocks.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <Button
                            :disabled="props.currentSync?.isRunning"
                            @click="submit"
                        >
                            <RefreshCcw class="size-4" />
                            Start live sync
                        </Button>
                        <p class="text-sm text-muted-foreground">
                            {{ props.currentSync?.isRunning ? 'Wait for the active sync to finish before starting another.' : 'Queue work onto the database-backed Laravel queue.' }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <section class="grid gap-4 xl:grid-cols-2">
                <SyncLogPanel
                    title="Current sync"
                    description="This panel polls while a sync is queued or running."
                    :sync-log="props.currentSync"
                />
                <SyncLogPanel
                    title="Latest completed sync"
                    description="Most recent terminal sync state, including partial errors."
                    :sync-log="props.latestSync"
                />
            </section>
        </div>
    </AppLayout>
</template>
