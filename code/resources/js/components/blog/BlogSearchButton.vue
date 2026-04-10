<script setup lang="ts">
function handleClick(): void {
    if (typeof window !== 'undefined') {
        window.alert('Search is not wired yet. It will be added in a later iteration.');
    }
}
</script>

<template>
    <button type="button" class="icon-pill" title="Search" @click="handleClick">
        <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.3-4.3" />
        </svg>
    </button>
</template>
