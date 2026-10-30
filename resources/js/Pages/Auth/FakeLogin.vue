<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AvisoCamposObrigatorios from '../../Components/AvisoCamposObrigatorios.vue';
import { focarPrimeiroErro } from '../../Components/focarPrimeiroErro';
import HomeLayout from '../../Layouts/HomeLayout.vue';
import type { ContaFake } from '../../types';

// Sem o topo e o rodapé do portal: a página imita a tela de login do gov.br.
defineOptions({ layout: HomeLayout });

const props = withDefaults(
    defineProps<{
        contas?: ContaFake[];
    }>(),
    { contas: () => [] },
);

/** Máscara do gov.br enquanto se digita: 52998224725 → 529.982.247-25. */
const formatarCpf = (valor: string): string =>
    valor
        .replace(/\D/g, '')
        .slice(0, 11)
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');

// O CPF da primeira conta de teste já vem preenchido.
const form = useForm({ cpf: formatarCpf(props.contas[0]?.cpf ?? '') });

// Só para a experiência: o simulado não confere a senha, e ela nunca sai do navegador.
const senha = ref<string>('senha-de-teste');
const senhaVisivel = ref<boolean>(false);
const erroSenha = ref<string>('');

const campoCpf = ref<HTMLInputElement | null>(null);
const campoSenha = ref<HTMLInputElement | null>(null);

// Atualiza o próprio campo: se a máscara descarta o que foi digitado, o valor do form não muda e o Vue não redesenha.
const digitarCpf = (evento: Event): void => {
    const campo = evento.target as HTMLInputElement;
    campo.value = formatarCpf(campo.value);
    form.cpf = campo.value;
    form.clearErrors('cpf');
};

const entrar = (): void => {
    const cpfIncompleto = form.cpf.replace(/\D/g, '').length !== 11;
    if (cpfIncompleto) form.setError('cpf', 'Digite os 11 números do CPF.');
    erroSenha.value = senha.value === '' ? 'Digite sua senha.' : '';

    if (cpfIncompleto || erroSenha.value) {
        (cpfIncompleto ? campoCpf : campoSenha).value?.focus();
        return;
    }
    // CPF recusado pelo servidor: o erro aparece no campo, que recebe o foco.
    form.post('/auth/govbr/fake', { onError: () => focarPrimeiroErro('#formulario-login') });
};

const usarConta = (conta: ContaFake): void => {
    form.cpf = formatarCpf(conta.cpf);
    form.clearErrors();
    campoCpf.value?.focus();
};
</script>

<template>
    <div class="flex flex-1 flex-col">
        <Head title="Acesse sua conta gov.br (simulação)" />

        <header class="border-b border-hairline bg-superficie">
            <div class="mx-auto flex min-h-[61px] max-w-6xl items-center gap-3 px-4">
                <!-- Marca em texto nas cores do gov.br, não a logomarca oficial. -->
                <span role="img" aria-label="gov.br" class="text-[1.875rem] leading-none font-extrabold tracking-[-0.04em]">
                    <span class="text-acao">g</span><span class="text-[#ffcd07]">o</span><span class="text-[#168821]">v</span><span class="text-acao">.br</span>
                </span>
                <span class="rounded-pill bg-aviso-fundo px-2.5 py-0.5 text-[0.75rem] font-bold tracking-[0.08em] text-aviso-texto uppercase">
                    Simulação
                </span>
            </div>
        </header>

        <!-- No notebook (tela-baixa) a página cabe sem rolagem; o clip evita a barra durante a subida da íris. -->
        <main id="conteudo" tabindex="-1" class="flex-1 overflow-clip focus:outline-none">
            <div class="mx-auto grid max-w-6xl gap-10 px-4 py-8 sm:py-12 lg:grid-cols-[minmax(0,1fr)_400px] lg:gap-14 lg:tela-baixa:py-5">
                <!-- Painel de abertura, como o do gov.br (sem a foto). Painel e formulário sobem quando se chega pela íris da home. -->
                <div aria-hidden="true" class="iris-surge relative hidden min-h-[380px] overflow-hidden rounded-card bg-parchment lg:block">
                    <div class="absolute inset-y-0 left-[9%] w-[16%] bg-[#ffcd07]"></div>
                    <div class="relative flex h-full flex-col justify-center gap-6 py-12 pr-10 pl-[33%]">
                        <p class="text-[5.5rem] leading-none font-extrabold tracking-[-0.05em]">
                            <span class="text-acao">g</span><span class="text-[#ffcd07]">o</span><span class="text-[#168821]">v</span><span class="text-acao">.br</span>
                        </p>
                        <p class="max-w-xs text-[1.1875rem] leading-[1.5]">
                            Com a <strong class="box-decoration-clone bg-[#ffcd07] px-1 text-[#1d1d1f]">conta gov.br</strong> você acessa os serviços
                            digitais do governo com um único login.
                        </p>
                    </div>
                </div>

                <section aria-labelledby="titulo-login" class="iris-surge iris-surge-depois lg:pt-6 lg:tela-baixa:pt-0">
                    <form id="formulario-login" novalidate @submit.prevent="entrar">
                        <h1 id="titulo-login" class="text-[1.25rem] font-bold tracking-normal">Identifique-se no gov.br com:</h1>

                        <p class="mt-6 flex items-center gap-3 font-semibold lg:tela-baixa:mt-4">
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6 shrink-0 text-acao" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <circle cx="9" cy="11" r="2" />
                                <path d="M6 16a3 3 0 0 1 6 0M15 10h3M15 14h3" />
                            </svg>
                            Número do CPF
                        </p>
                        <p class="mt-3 text-[0.9375rem] lg:tela-baixa:mt-2">Digite seu CPF e sua senha para <strong>acessar</strong> sua conta gov.br</p>

                        <AvisoCamposObrigatorios class="mt-3 lg:tela-baixa:mt-1" />

                        <label for="cpf" class="mt-5 block font-semibold lg:tela-baixa:mt-3">CPF<span aria-hidden="true" class="ml-0.5 text-invalido-texto">*</span></label>
                        <input
                            id="cpf"
                            ref="campoCpf"
                            :value="form.cpf"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            maxlength="14"
                            required
                            placeholder="Digite seu CPF"
                            class="mt-1 h-11 w-full rounded border border-tinta-suave bg-superficie px-4 text-tinta tabular-nums placeholder:text-tinta-suave placeholder:italic"
                            :aria-invalid="form.errors.cpf ? true : undefined"
                            :aria-describedby="form.errors.cpf ? 'cpf-erro' : undefined"
                            @input="digitarCpf"
                        />
                        <p v-if="form.errors.cpf" id="cpf-erro" role="alert" class="mt-2 flex items-start gap-2 text-[0.9375rem] font-semibold text-invalido-texto">
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="mt-0.5 size-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <circle cx="12" cy="12" r="9" />
                                <path d="m15 9-6 6M9 9l6 6" />
                            </svg>
                            {{ form.errors.cpf }}
                        </p>

                        <label for="senha" class="mt-5 block font-semibold lg:tela-baixa:mt-4">Senha</label>
                        <div class="relative mt-1">
                            <input
                                id="senha"
                                ref="campoSenha"
                                v-model="senha"
                                :type="senhaVisivel ? 'text' : 'password'"
                                autocomplete="off"
                                placeholder="Digite sua senha atual"
                                class="h-11 w-full rounded border border-tinta-suave bg-superficie pr-12 pl-4 text-tinta placeholder:text-tinta-suave placeholder:italic"
                                :aria-invalid="erroSenha ? true : undefined"
                                :aria-describedby="erroSenha ? 'senha-erro' : undefined"
                                @input="erroSenha = ''"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 inline-flex w-12 items-center justify-center text-acao"
                                aria-label="Mostrar senha"
                                :aria-pressed="senhaVisivel ? 'true' : 'false'"
                                @click="senhaVisivel = !senhaVisivel"
                            >
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                                    <circle cx="12" cy="12" r="3" />
                                    <path v-if="senhaVisivel" d="M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="erroSenha" id="senha-erro" role="alert" class="mt-2 flex items-start gap-2 text-[0.9375rem] font-semibold text-invalido-texto">
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="mt-0.5 size-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <circle cx="12" cy="12" r="9" />
                                <path d="m15 9-6 6M9 9l6 6" />
                            </svg>
                            {{ erroSenha }}
                        </p>

                        <div class="mt-6 flex justify-end lg:tela-baixa:mt-4">
                            <button
                                type="submit"
                                class="pressionavel inline-flex min-h-11 items-center rounded-pill bg-govbr px-9 font-bold text-sobre-govbr hover:brightness-110 disabled:opacity-60"
                                :disabled="form.processing"
                            >
                                Entrar
                            </button>
                        </div>
                    </form>

                    <aside aria-labelledby="simulacao-titulo" class="mt-10 rounded-card border border-hairline bg-parchment p-5 text-[0.9375rem] lg:tela-baixa:mt-4 lg:tela-baixa:p-4">
                        <h2 id="simulacao-titulo" class="text-[1rem] font-bold tracking-normal">Este é um login simulado</h2>
                        <p class="mt-1 text-tinta-suave">
                            Nenhum dado é enviado ao gov.br e a senha não é conferida. Para entrar como outra pessoa, use uma conta de teste:
                        </p>
                        <ul class="mt-3 flex flex-col gap-2 lg:tela-baixa:mt-2">
                            <li v-for="conta in contas" :key="conta.cpf">
                                <button
                                    type="button"
                                    class="flex min-h-11 w-full flex-wrap items-center justify-between gap-x-3 rounded-sm border border-hairline bg-superficie px-4 py-2 text-left hover:border-acao"
                                    @click="usarConta(conta)"
                                >
                                    <span class="font-semibold"><span class="sr-only">Usar a conta de </span>{{ conta.nome }}</span>
                                    <span class="text-tinta-suave tabular-nums"><span class="sr-only">, CPF </span>{{ formatarCpf(conta.cpf) }}</span>
                                </button>
                            </li>
                        </ul>
                    </aside>
                </section>
            </div>
        </main>
    </div>
</template>
