<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CookieNotice from '../Components/CookieNotice.vue';
import FlashMessage from '../Components/FlashMessage.vue';
import SiteFooter from '../Components/SiteFooter.vue';
import SiteHeader from '../Components/SiteHeader.vue';
import SkipLink from '../Components/SkipLink.vue';
import TestEnvBanner from '../Components/TestEnvBanner.vue';

const ambienteTeste = computed(() => usePage().props.ambienteTeste);
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <SkipLink />
        <TestEnvBanner v-if="ambienteTeste" />
        <SiteHeader />

        <main id="conteudo" tabindex="-1" class="flex-1 focus:outline-none">
            <FlashMessage />
            <slot />
        </main>

        <SiteFooter />
        <CookieNotice />
    </div>
</template>
