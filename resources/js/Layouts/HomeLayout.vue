<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CookieNotice from '../Components/CookieNotice.vue';
import SkipLink from '../Components/SkipLink.vue';
import TestEnvBanner from '../Components/TestEnvBanner.vue';
import TransicaoIris from '../Components/TransicaoIris.vue';

const ambienteTeste = computed(() => usePage().props.ambienteTeste);
</script>

<template>
    <!-- Página inicial: o topo, o <main> e o rodapé ficam na própria página (anexo portal-fgm.html). -->
    <div class="flex min-h-screen flex-col">
        <SkipLink />
        <TestEnvBanner v-if="ambienteTeste" />
        <slot />
        <CookieNotice />
        <TransicaoIris />
    </div>
</template>
