<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FaixaPortal from '../../Components/FaixaPortal.vue';
import PortalProponenteLayout from '../../Layouts/PortalProponenteLayout.vue';

defineOptions({ layout: PortalProponenteLayout });
</script>

<template>
    <div>
        <Head title="Projetos" />

        <FaixaPortal aria-labelledby="projetos-titulo">
            <div class="flex flex-col gap-3">
                <p class="text-[0.75rem] font-semibold tracking-[0.24em] text-white/75 uppercase">Portal do patrocinador</p>
                <h1 id="projetos-titulo" class="font-barlow text-[2.6rem] leading-[0.95] font-extrabold tracking-[0.01em] break-words uppercase md:text-[3.5rem]">
                    Projetos
                </h1>
                <p class="max-w-2xl text-base leading-[1.7] text-white/85 md:text-[1.0625rem]">
                    Conheça os projetos culturais dos proponentes e encontre oportunidades de patrocínio.
                </p>
            </div>
        </FaixaPortal>

        <div class="relative mx-auto -mt-20 max-w-6xl px-4 pb-16 md:-mt-24">
            <!-- Ainda não há envio de projetos pelos proponentes; quando houver, a lista entra aqui. -->
            <section
                aria-labelledby="projetos-vazio-titulo"
                class="flex flex-col items-center gap-4 rounded-[24px] border border-hairline bg-superficie px-6 py-14 text-center shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)] md:py-20"
            >
                <span aria-hidden="true" class="inline-flex size-14 items-center justify-center rounded-full bg-nevoa text-acao">
                    <svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                    </svg>
                </span>
                <h2 id="projetos-vazio-titulo" class="text-[1.25rem] font-bold text-home-tinta">Ainda não há projetos cadastrados</h2>
                <p class="max-w-xl leading-[1.6] text-tinta-suave">
                    Quando os proponentes cadastrarem seus projetos, eles aparecerão aqui para você conhecer as propostas.
                </p>
            </section>
        </div>
    </div>
</template>
