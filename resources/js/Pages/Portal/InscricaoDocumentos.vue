<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import FaixaPortal from '../../Components/FaixaPortal.vue';
import PortalProponenteLayout from '../../Layouts/PortalProponenteLayout.vue';
import type { PortalInscricaoDocumentosProps } from '../../types';

defineOptions({ layout: PortalProponenteLayout });

const props = defineProps<PortalInscricaoDocumentosProps>();

const rotulosDasCotas = computed(() =>
    props.cotas.filter((cota) => props.inscricao.cotas.includes(cota.valor)).map((cota) => cota.rotulo),
);
</script>

<template>
    <div>
        <Head :title="`Inscrição — ${edital.titulo}`" />

        <FaixaPortal aria-labelledby="inscricao-titulo">
            <div class="flex flex-col gap-3">
                <!-- Caminho da rota portal.editais.inscricao (o front não tem helper de rotas nomeadas). -->
                <Link
                    :href="`/portal/editais/${edital.slug}/inscricao`"
                    class="inline-flex min-h-11 items-center gap-2 self-start text-[0.8125rem] font-semibold tracking-[0.12em] text-white/85 uppercase no-underline hover:text-white"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M11 6l-6 6 6 6" />
                    </svg>
                    Voltar às inscrições do edital
                </Link>
                <h1 id="inscricao-titulo" class="font-barlow text-[2.6rem] leading-[0.95] font-extrabold tracking-[0.01em] break-words uppercase md:text-[3.5rem]">
                    Inscrição
                </h1>
                <p class="max-w-2xl text-base leading-[1.7] text-white/85 md:text-[1.0625rem]">
                    <strong class="font-semibold text-white">{{ edital.titulo }}</strong> · {{ edital.categoria }}<br />
                    {{ inscricao.situacaoRotulo }}<template v-if="inscricao.numero"> · nº {{ inscricao.numero }}</template>
                </p>
            </div>
        </FaixaPortal>

        <div class="relative mx-auto -mt-20 flex max-w-6xl flex-col gap-6 px-4 pb-16 md:-mt-24">
            <section
                aria-labelledby="escolhas-titulo"
                class="rounded-[24px] border border-hairline bg-superficie p-6 shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)] md:p-10"
            >
                <h2 id="escolhas-titulo" class="text-[1.25rem] font-bold">Suas escolhas</h2>
                <dl class="mt-4 grid gap-x-8 gap-y-3 md:grid-cols-3">
                    <div>
                        <dt class="text-[0.8125rem] font-semibold text-tinta-suave">Cadastro</dt>
                        <dd class="break-words">{{ inscricao.cadastro }}</dd>
                    </div>
                    <div>
                        <dt class="text-[0.8125rem] font-semibold text-tinta-suave">Categoria</dt>
                        <dd class="break-words">{{ inscricao.categoria ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[0.8125rem] font-semibold text-tinta-suave">Cotas</dt>
                        <dd>{{ rotulosDasCotas.length > 0 ? rotulosDasCotas.join(', ') : 'Sem cota' }}</dd>
                    </div>
                </dl>
            </section>

            <section
                aria-labelledby="documentos-titulo"
                class="rounded-[24px] border border-hairline bg-superficie p-6 shadow-[0_24px_60px_-34px_rgba(6,36,74,0.45)] md:p-10"
            >
                <h2 id="documentos-titulo" class="text-[1.25rem] font-bold">Documentos exigidos</h2>
                <p v-if="documentos.length === 0" class="mt-2 leading-[1.6] text-tinta-suave">Nenhum documento é exigido para as escolhas desta inscrição.</p>
                <ul v-else class="mt-4 divide-y divide-hairline border-y border-hairline">
                    <li v-for="documento in documentos" :key="documento.id" class="flex flex-col gap-1 py-3">
                        <p class="font-semibold break-words">
                            {{ documento.nome }}
                            <span class="font-normal text-tinta-suave">· {{ documento.obrigatorio ? 'Obrigatório' : 'Opcional' }} · {{ documento.etapaRotulo }}</span>
                        </p>
                        <p v-if="documento.orientacao" class="text-[0.875rem] leading-[1.5] text-tinta-suave">{{ documento.orientacao }}</p>
                        <a v-if="documento.modeloUrl" :href="documento.modeloUrl" class="text-[0.875rem] font-semibold text-acao">
                            Baixar modelo<span v-if="documento.modeloNome" class="sr-only"> ({{ documento.modeloNome }})</span>
                        </a>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
