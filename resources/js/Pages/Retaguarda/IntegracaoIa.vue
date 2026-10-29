<script setup lang="ts">
import { computed, nextTick, reactive, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import CamposProvedorIa from '../../Components/Retaguarda/CamposProvedorIa.vue';
import SecaoCadastro from '../../Components/SecaoCadastro.vue';
import RetaguardaLayout from '../../Layouts/RetaguardaLayout.vue';
import type { ProvedorIa, ProvedorIaRetaguarda, RetaguardaIntegracaoIaProps } from '../../types';

defineOptions({ layout: RetaguardaLayout });

const props = defineProps<RetaguardaIntegracaoIaProps>();

// Caminhos das rotas retaguarda.integracao-ia.update e .redundancia (o front não tem helper de rotas nomeadas).
const URL_PRINCIPAL = '/retaguarda/integracao-ia';
const URL_REDUNDANCIA = '/retaguarda/integracao-ia/redundancia';

const salvo = (provedor: ProvedorIa | null): ProvedorIaRetaguarda | null => props.provedores.find((item) => item.valor === provedor) ?? null;

// A chave nunca volta do servidor: o campo começa vazio e, em branco, mantém a guardada.
const formPrincipal = useForm({
    provedor: (props.configuracao.principal ?? '') as ProvedorIa | '',
    modelo: salvo(props.configuracao.principal)?.modelo ?? '',
    chave_api: '',
    ativo: props.configuracao.ativo,
});

const formRedundancia = useForm({
    provedor: (props.configuracao.redundancia ?? '') as ProvedorIa | '',
    modelo: salvo(props.configuracao.redundancia)?.modelo ?? '',
    chave_api: '',
});

// Modelos já buscados, por provedor: valem para os dois cartões enquanto a página está aberta.
const modelosBuscados = reactive<Partial<Record<ProvedorIa, string[]>>>({});
const guardarModelos = (provedor: ProvedorIa, modelos: string[]): void => {
    modelosBuscados[provedor] = modelos;
};

const principalSalvo = computed(() => salvo(props.configuracao.principal));
const redundanciaSalva = computed(() => salvo(props.configuracao.redundancia));
const opcoesDeRedundancia = computed(() => props.provedores.filter((item) => item.valor !== props.configuracao.principal));

const avisoDeTroca = computed((): string =>
    principalSalvo.value && formPrincipal.provedor !== '' && formPrincipal.provedor === props.configuracao.redundancia
        ? `Ao salvar, os dois trocam de lugar: ${principalSalvo.value.rotulo} passa a ser a redundância.`
        : '',
);

const focarPrimeiroInvalido = (formulario: string): void => {
    void nextTick(() => document.querySelector<HTMLElement>(`#${formulario} [aria-invalid="true"]`)?.focus());
};

// O put preserva o estado da página: a chave digitada sai do campo à mão depois de salvar.
const salvarPrincipal = (): void => {
    formPrincipal.put(URL_PRINCIPAL, {
        preserveScroll: true,
        onError: () => focarPrimeiroInvalido('formulario-ia-principal'),
        onSuccess: () => {
            formPrincipal.reset('chave_api');
            formPrincipal.defaults();
        },
    });
};

const salvarRedundancia = (): void => {
    formRedundancia.put(URL_REDUNDANCIA, {
        preserveScroll: true,
        onError: () => focarPrimeiroInvalido('formulario-ia-redundancia'),
        onSuccess: () => {
            formRedundancia.reset('chave_api');
            formRedundancia.defaults();
        },
    });
};

// Quando a ordem salva muda (inclusive na troca de lugar feita pelo cartão 1), o cartão da redundância volta ao que está salvo.
watch(
    () => [props.configuracao.principal, props.configuracao.redundancia] as const,
    ([, redundancia]) => {
        formRedundancia.defaults({ provedor: redundancia ?? '', modelo: salvo(redundancia)?.modelo ?? '', chave_api: '' });
        formRedundancia.reset();
        formRedundancia.clearErrors();
    },
);

const classeSelo = 'inline-flex rounded-pill px-2.5 py-0.5 text-[0.8125rem] font-semibold';
const classeBotaoSalvar =
    'pressionavel inline-flex min-h-12 items-center justify-center gap-2 rounded-pill bg-acao px-8 text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase hover:bg-acao-escuro disabled:cursor-wait disabled:opacity-70';
const classeElo = 'flex min-w-0 items-center gap-3 rounded-[12px] border border-hairline px-4 py-3';
const classeEtiqueta = 'shrink-0 rounded-pill bg-nevoa px-2.5 py-0.5 text-[0.75rem] font-bold tracking-[0.06em] text-acao uppercase';
</script>

<template>
    <div class="mx-auto flex max-w-4xl flex-col gap-6 px-4 pt-8 lg:px-6">
        <Head title="Integração com IA · Retaguarda" />

        <div>
            <p class="text-[0.75rem] font-bold tracking-[0.12em] text-acao uppercase">Parametrização</p>
            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-2">
                <h1 class="text-[1.75rem] leading-tight font-bold">Integração com IA</h1>
                <span :class="[classeSelo, configuracao.ativo ? 'bg-nevoa text-acao' : 'border border-hairline text-tinta-suave']">
                    {{ configuracao.ativo ? 'Validação por IA ligada' : 'Validação por IA desligada' }}
                </span>
            </div>
            <p class="mt-1 text-[0.9375rem] text-tinta-suave">
                Provedores de inteligência artificial usados nas validações automáticas do sistema. As chaves ficam cifradas no banco e não voltam
                para a tela. Com a validação ligada, os documentos analisados são enviados ao provedor escolhido.
            </p>
        </div>

        <form id="formulario-ia-principal" novalidate @submit.prevent="salvarPrincipal">
            <SecaoCadastro id="secao-ia-principal" :numero="1" titulo="Provedor principal">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9zM19 15v4M17 17h4M5 3v3M3.5 4.5h3" />
                    </svg>
                </template>

                <CamposProvedorIa
                    v-model:provedor="formPrincipal.provedor"
                    v-model:modelo="formPrincipal.modelo"
                    v-model:chave="formPrincipal.chave_api"
                    id="ia-principal"
                    :provedores="provedores"
                    :erros="formPrincipal.errors"
                    :modelos-buscados="modelosBuscados"
                    @limpar-erros="(campos) => formPrincipal.clearErrors(...campos)"
                    @buscou="guardarModelos"
                >
                    <p v-if="avisoDeTroca" class="flex items-start gap-2 rounded-[12px] bg-nevoa px-4 py-3 font-semibold text-acao">
                        <svg aria-hidden="true" viewBox="0 0 24 24" class="mt-0.5 size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M7 4 3 8l4 4M3 8h14M17 20l4-4-4-4M21 16H7" />
                        </svg>
                        {{ avisoDeTroca }}
                    </p>

                    <label class="flex min-h-12 cursor-pointer items-center justify-between gap-4 rounded-[12px] border border-hairline px-4 py-3">
                        <span class="flex flex-col">
                            <span id="ia-ativo-rotulo" class="font-semibold">Ativar validação por IA</span>
                            <span id="ia-ativo-ajuda" class="text-[0.8125rem] text-tinta-suave">
                                Ligada, as validações automáticas usam este provedor (e a redundância, quando ele falhar).
                            </span>
                        </span>
                        <input
                            v-model="formPrincipal.ativo"
                            type="checkbox"
                            role="switch"
                            class="peer sr-only"
                            aria-labelledby="ia-ativo-rotulo"
                            aria-describedby="ia-ativo-ajuda"
                            @change="formPrincipal.clearErrors('ativo')"
                        />
                        <span
                            aria-hidden="true"
                            class="relative inline-flex h-6 w-10 shrink-0 rounded-pill transition-colors peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-acao motion-reduce:transition-none"
                            :class="formPrincipal.ativo ? 'bg-acao' : 'bg-hairline'"
                        >
                            <span
                                class="absolute top-0.5 left-0.5 size-5 rounded-full border border-hairline bg-superficie transition-transform motion-reduce:transition-none"
                                :class="{ 'translate-x-4': formPrincipal.ativo }"
                            ></span>
                        </span>
                    </label>
                    <p v-if="formPrincipal.errors.ativo" class="text-[0.875rem] font-semibold text-erro-texto">{{ formPrincipal.errors.ativo }}</p>

                    <template #acoes>
                        <button type="submit" :disabled="formPrincipal.processing" :class="classeBotaoSalvar">
                            {{ formPrincipal.processing ? 'Salvando…' : 'Salvar configuração' }}
                        </button>
                    </template>
                </CamposProvedorIa>

                <p v-if="formPrincipal.hasErrors" role="alert" class="mt-4 text-[0.9375rem] font-semibold text-erro-texto sm:text-right">
                    Confira os campos destacados acima.
                </p>
            </SecaoCadastro>
        </form>

        <form id="formulario-ia-redundancia" novalidate @submit.prevent="salvarRedundancia">
            <SecaoCadastro id="secao-ia-redundancia" :numero="2" titulo="Redundância">
                <template #icone>
                    <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6zM9 12l2 2 4-4" />
                    </svg>
                </template>

                <div class="flex flex-col gap-5">
                    <p class="text-[0.9375rem] text-tinta-suave">
                        A chamada que o provedor principal não atender é repetida no provedor de redundância. Ele não substitui o principal: entra só na
                        chamada que falhou, e o principal continua sendo o primeiro na seguinte.
                    </p>

                    <p v-if="!principalSalvo" class="flex items-start gap-2 rounded-[12px] bg-nevoa px-4 py-3 font-semibold text-acao">
                        <svg aria-hidden="true" viewBox="0 0 24 24" class="mt-0.5 size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 8h.01M11 12h1v5h1" />
                        </svg>
                        Salve o provedor principal para escolher a redundância.
                    </p>

                    <template v-else>
                        <ol aria-label="Ordem de uso dos provedores" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <li :class="[classeElo, 'bg-nevoa']">
                                <span :class="classeEtiqueta">Principal</span>
                                <span class="flex min-w-0 flex-col">
                                    <span class="font-semibold">{{ principalSalvo.rotulo }}</span>
                                    <span class="text-[0.8125rem] break-words text-tinta-suave">{{ principalSalvo.modelo }}</span>
                                </span>
                            </li>
                            <li class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <svg
                                    aria-hidden="true"
                                    viewBox="0 0 24 24"
                                    class="ml-6 size-5 shrink-0 rotate-90 text-tinta-suave sm:ml-0 sm:rotate-0"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M5 12h14M13 6l6 6-6 6" />
                                </svg>
                                <span :class="classeElo">
                                    <span :class="classeEtiqueta">Redundância</span>
                                    <span v-if="redundanciaSalva" class="flex min-w-0 flex-col">
                                        <span class="font-semibold">{{ redundanciaSalva.rotulo }}</span>
                                        <span class="text-[0.8125rem] break-words text-tinta-suave">{{ redundanciaSalva.modelo }}</span>
                                    </span>
                                    <span v-else class="font-semibold text-tinta-suave">Sem redundância</span>
                                </span>
                            </li>
                        </ol>

                        <CamposProvedorIa
                            v-model:provedor="formRedundancia.provedor"
                            v-model:modelo="formRedundancia.modelo"
                            v-model:chave="formRedundancia.chave_api"
                            id="ia-redundancia"
                            :provedores="opcoesDeRedundancia"
                            vazio="Sem redundância"
                            :erros="formRedundancia.errors"
                            :modelos-buscados="modelosBuscados"
                            @limpar-erros="(campos) => formRedundancia.clearErrors(...campos)"
                            @buscou="guardarModelos"
                        >
                            <template #acoes>
                                <button type="submit" :disabled="formRedundancia.processing" :class="classeBotaoSalvar">
                                    {{ formRedundancia.processing ? 'Salvando…' : 'Salvar redundância' }}
                                </button>
                            </template>
                        </CamposProvedorIa>

                        <p v-if="formRedundancia.hasErrors" role="alert" class="text-[0.9375rem] font-semibold text-erro-texto sm:text-right">
                            Confira os campos destacados acima.
                        </p>
                    </template>
                </div>
            </SecaoCadastro>
        </form>
    </div>
</template>
