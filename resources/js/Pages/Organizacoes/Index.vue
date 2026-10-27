<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PortalProponenteLayout from '../../Layouts/PortalProponenteLayout.vue';
import type { OrganizacoesIndexProps } from '../../types';

defineOptions({ layout: PortalProponenteLayout });

defineProps<OrganizacoesIndexProps>();
</script>

<template>
    <div class="mx-auto max-w-6xl px-4 py-10">
        <Head title="Minhas Pessoas Jurídicas" />

        <h1 class="font-barlow text-3xl font-extrabold uppercase">Minhas Pessoas Jurídicas</h1>

        <ul class="mt-6 flex flex-col gap-3">
            <li v-for="organizacao in organizacoes" :key="organizacao.id">
                <Link :href="`/organizacoes/${organizacao.id}/editar`">{{ organizacao.nome }} · {{ organizacao.cnpj }}</Link>
            </li>
        </ul>
    </div>
</template>
