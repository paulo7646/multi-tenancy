<script setup>
defineProps({
    tenant: { type: Object, default: () => ({}) },
    adminUrl: { type: String, default: '/admin' },
});
</script>

<template>
    <AppLayout title="obra3" :admin-url="adminUrl">
        <PageHeader title="Bem-vindo" subtitle="Página da empresa obra3" />

        <v-card>
            <v-card-text>
                Edite este ambiente em <code>views/clientes/obra3</code>.
            </v-card-text>
        </v-card>
    </AppLayout>
</template>
