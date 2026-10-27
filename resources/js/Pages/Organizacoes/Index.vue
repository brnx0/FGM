<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import FaixaPortal from '../../Components/FaixaPortal.vue';
import PortalProponenteLayout from '../../Layouts/PortalProponenteLayout.vue';
import type { OrganizacoesIndexProps } from '../../types';

defineOptions({ layout: PortalProponenteLayout });

defineProps<OrganizacoesIndexProps>();
</script>

<template>
    <div>
        <Head title="Minhas Pessoas Jurídicas" />

        <!-- Com a FaixaPortal, como as outras telas do portal: é nela que fica o menu. -->
        <FaixaPortal aria-labelledby="organizacoes-titulo">
            <div class="flex flex-col gap-3">
                <p class="text-[0.75rem] font-semibold tracking-[0.24em] text-white/75 uppercase">Portal do proponente</p>
                <h1 id="organizacoes-titulo" class="font-barlow text-[2.6rem] leading-[0.95] font-extrabold tracking-[0.01em] break-words uppercase md:text-[3.5rem]">
                    Minhas Pessoas Jurídicas
                </h1>
            </div>
        </FaixaPortal>

        <div class="relative mx-auto -mt-20 max-w-6xl px-4 pb-16 md:-mt-24">
            <p
                v-if="!organizacoes.length"
                class="rounded-[24px] border border-hairline bg-superficie px-6 py-14 text-center text-tinta-suave shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)]"
            >
                Nenhuma Pessoa Jurídica cadastrada.
            </p>
            <ul v-else class="flex flex-col divide-y divide-hairline rounded-[24px] border border-hairline bg-superficie px-6 shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)] md:px-10">
                <li v-for="organizacao in organizacoes" :key="organizacao.id">
                    <Link
                        :href="`/organizacoes/${organizacao.id}/editar`"
                        class="flex min-h-16 items-center justify-between gap-4 py-4 font-semibold text-home-tinta no-underline hover:text-acao"
                    >
                        <span>
                            {{ organizacao.nome }}
                            <span class="block text-[0.875rem] font-normal text-tinta-suave tabular-nums">{{ organizacao.cnpj }}</span>
                        </span>
                        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m9 6 6 6-6 6" />
                        </svg>
                    </Link>
                </li>
            </ul>
        </div>
    </div>
</template>
