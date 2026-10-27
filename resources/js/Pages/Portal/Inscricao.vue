<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import FaixaPortal from '../../Components/FaixaPortal.vue';
import PortalProponenteLayout from '../../Layouts/PortalProponenteLayout.vue';
import type { PortalInscricaoProps } from '../../types';

defineOptions({ layout: PortalProponenteLayout });

defineProps<PortalInscricaoProps>();
</script>

<template>
    <div>
        <Head :title="`Inscrição — ${edital.titulo}`" />

        <FaixaPortal aria-labelledby="inscricao-titulo">
            <div class="flex flex-col gap-3">
                <!-- Caminho da rota portal.editais (o front não tem helper de rotas nomeadas). -->
                <Link
                    href="/portal/editais"
                    class="inline-flex min-h-11 items-center gap-2 self-start text-[0.8125rem] font-semibold tracking-[0.12em] text-white/85 uppercase no-underline hover:text-white"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M11 6l-6 6 6 6" />
                    </svg>
                    Voltar aos editais
                </Link>
                <h1 id="inscricao-titulo" class="font-barlow text-[2.6rem] leading-[0.95] font-extrabold tracking-[0.01em] break-words uppercase md:text-[3.5rem]">
                    Inscrição
                </h1>
                <p class="max-w-2xl text-base leading-[1.7] text-white/85 md:text-[1.0625rem]">
                    <strong class="font-semibold text-white">{{ edital.titulo }}</strong> · {{ edital.categoria }}<br />
                    Inscrições de {{ edital.periodo }}.
                </p>
            </div>
        </FaixaPortal>

        <div class="relative mx-auto -mt-20 max-w-6xl px-4 pb-16 md:-mt-24">
            <!-- O envio dos documentos ainda será feito; quando houver, o formulário entra aqui. -->
            <section
                aria-labelledby="documentos-titulo"
                class="flex flex-col items-center gap-4 rounded-[24px] border border-hairline bg-superficie px-6 py-14 text-center shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)] md:py-20"
            >
                <span aria-hidden="true" class="inline-flex size-14 items-center justify-center rounded-full bg-nevoa text-acao">
                    <svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m21 11-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 7" />
                    </svg>
                </span>
                <h2 id="documentos-titulo" class="text-[1.25rem] font-bold text-home-tinta">Anexar documentos</h2>
                <p class="max-w-xl leading-[1.6] text-tinta-suave">
                    Em breve você poderá anexar aqui os documentos exigidos para concluir a inscrição. Enquanto isso, confira no PDF do edital quais
                    documentos separar.
                </p>
                <a
                    :href="edital.pdf"
                    target="_blank"
                    rel="noopener"
                    class="pressionavel inline-flex min-h-12 items-center justify-center rounded-pill bg-acao px-6 text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase no-underline hover:bg-acao-escuro"
                >
                    PDF do edital<span class="sr-only"> (abre em nova aba)</span>
                </a>
            </section>
        </div>
    </div>
</template>
