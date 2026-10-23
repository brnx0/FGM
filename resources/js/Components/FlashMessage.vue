<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const erro = computed(() => usePage().props.flash?.erro);
</script>

<template>
    <div v-if="erro" role="alert" class="mx-auto mt-6 max-w-5xl px-4">
        <p class="rounded-card border border-[#F5C2C0] bg-[#FDECEC] px-5 py-4 text-[#8A1C1C]">
            {{ erro }}
        </p>
    </div>
</template>
