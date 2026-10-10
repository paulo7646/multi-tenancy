<script setup>
defineProps({
    tenant: { type: Object, default: () => ({}) },
    adminUrl: { type: String, default: '/admin' },
});
</script>

<template>
    <AppLayout title="linear" :admin-url="adminUrl">
        <PageHeader title="Bem-vindo" subtitle="Página da empresa linear" />

        <v-card>
            <v-card-text>
                Edite este ambiente em <code>views/clientes/linear</code>.
            </v-card-text>
        </v-card>
    </AppLayout>
</template>
