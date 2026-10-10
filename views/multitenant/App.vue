<script setup>
defineProps({
    appName: { type: String, default: '' },
    adminUrl: { type: String, default: '/admin' },
});
</script>

<template>
    <AppLayout :title="appName" :admin-url="adminUrl">
        <PageHeader title="Sistema Multitenant" subtitle="Página principal do sistema" />

        <v-card>
            <v-card-text>
                Edite este ambiente em <code>views/multitenant</code>.
            </v-card-text>
        </v-card>
    </AppLayout>
</template>
