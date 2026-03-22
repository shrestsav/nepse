<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    values: number[];
}>();

const barHeights = computed(() => {
    if (props.values.length === 0) {
        return [];
    }

    const minimum = Math.min(...props.values);
    const maximum = Math.max(...props.values);
    const span = maximum - minimum || 1;

    return props.values.map((value) => {
        const normalized = (value - minimum) / span;

        return `${Math.max(22, Math.round(normalized * 100))}%`;
    });
});
</script>

<template>
    <div class="flex h-14 items-end gap-1 rounded-lg bg-muted/40 p-2">
        <div
            v-for="(height, index) in barHeights"
            :key="`${height}-${index}`"
            class="min-w-0 flex-1 rounded-sm bg-primary/70"
            :style="{ height }"
        />
        <div
            v-if="barHeights.length === 0"
            class="flex h-full w-full items-center justify-center text-xs text-muted-foreground"
        >
            No data
        </div>
    </div>
</template>
