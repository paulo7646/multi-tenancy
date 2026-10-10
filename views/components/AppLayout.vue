<script setup>
defineProps({
    title: { type: String, default: '' },
    adminUrl: { type: String, default: '/admin' },
});
</script>

<template>
    <v-app>
        <v-app-bar color="primary" flat>
            <v-app-bar-title>
                <slot name="title">{{ title }}</slot>
            </v-app-bar-title>

            <template #append>
                <slot name="actions">
                    <v-btn :href="adminUrl" variant="text" prepend-icon="mdi-shield-account">
                        Administração
                    </v-btn>
                </slot>
            </template>
        </v-app-bar>

        <v-main>
            <v-container>
                <slot />
            </v-container>
        </v-main>
    </v-app>
</template>
