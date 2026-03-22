<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

defineProps<{
    title: string;
    value: number | string;
    description: string;
    href?: NonNullable<InertiaLinkProps['href']> | null;
}>();
</script>

<template>
    <Card class="border-border/60">
        <CardHeader class="space-y-1">
            <CardDescription>{{ title }}</CardDescription>
            <CardTitle class="text-3xl font-semibold tracking-tight">
                {{ value }}
            </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
            <p class="text-sm text-muted-foreground">
                {{ description }}
            </p>
            <Link
                v-if="href"
                :href="href"
                class="mt-4 inline-flex text-sm font-medium text-primary underline-offset-4 hover:underline"
            >
                Open
            </Link>
        </CardContent>
    </Card>
</template>
