<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="d-flex align-center justify-space-between flex-wrap ga-4 my-6">
        <div>
            <h1 class="text-h4 font-weight-bold">{{ title }}</h1>
            <p v-if="subtitle" class="text-medium-emphasis mt-1">{{ subtitle }}</p>
        </div>

        <div>
            <slot name="actions" />
        </div>
    </div>
</template>
