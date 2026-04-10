<script setup lang="ts">
defineProps<{
    html: string;
}>();
</script>

<template>
    <section class="content" v-html="html" />
</template>
