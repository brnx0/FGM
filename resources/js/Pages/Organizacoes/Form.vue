<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PortalProponenteLayout from '../../Layouts/PortalProponenteLayout.vue';
import type { OrganizacaoFormProps } from '../../types';

defineOptions({ layout: PortalProponenteLayout });

defineProps<OrganizacaoFormProps>();
</script>

<template>
    <div class="mx-auto max-w-6xl px-4 py-10">
        <Head :title="organizacao ? 'Editar organização' : 'Cadastrar organização'" />

        <h1 class="font-barlow text-3xl font-extrabold uppercase">{{ organizacao ? 'Editar organização' : 'Cadastrar organização' }}</h1>
    </div>
</template>
