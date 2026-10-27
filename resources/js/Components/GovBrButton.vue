<script setup lang="ts">
withDefaults(
    defineProps<{
        size?: 'sm' | 'lg';
        /** Início do login: o do portal do proponente, ou o da retaguarda. */
        href?: string;
    }>(),
    { size: 'lg', href: '/auth/govbr/redirect' },
);
</script>

<template>
    <!-- Link comum (não Inertia): a rota redireciona para fora do portal. -->
    <a
        :href="href"
        class="pressionavel inline-flex min-h-11 items-center justify-center gap-2 rounded-pill bg-govbr font-semibold whitespace-nowrap text-sobre-govbr no-underline hover:brightness-110"
        :class="size === 'sm' ? 'px-4 text-[0.9375rem]' : 'min-h-12 px-6 text-corpo'"
    >
        <svg
            aria-hidden="true"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            :class="size === 'sm' ? 'size-4' : 'size-5'"
        >
            <circle cx="12" cy="8" r="4" />
            <path d="M4 21a8 8 0 0 1 16 0" />
        </svg>
        Entrar com gov.br
    </a>
</template>
