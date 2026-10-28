<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import CampoCadastro from '../CampoCadastro.vue';
import { buscarModelosIa, testarConexaoIa } from './conexaoIa';
import { classeCampoBase } from './listaEditavel';
import type { ProvedorIa, ProvedorIaRetaguarda } from '../../types';

/**
 * Provedor, chave da API e modelo de um cartão da Integração com IA (principal ou redundância), com "Buscar" modelos e
 * "Testar conexão". O slot padrão entra antes dos botões; o slot "acoes" fica ao lado de "Testar conexão" (o salvar).
 */
type CampoIa = 'provedor' | 'modelo' | 'chave_api';

const props = defineProps<{
    /** Prefixo dos ids dos campos: um por cartão. */
    id: string;
    /** Provedores que podem ser escolhidos neste cartão. */
    provedores: ProvedorIaRetaguarda[];
    /** Texto da opção vazia (ex.: "Sem redundância"); sem ele, escolher um provedor é obrigatório. */
    vazio?: string;
    erros: Partial<Record<CampoIa, string>>;
    /** Listas já buscadas, por provedor, compartilhadas pelos dois cartões. */
    modelosBuscados: Partial<Record<ProvedorIa, string[]>>;
}>();

const emit = defineEmits<{
    limparErros: [campos: CampoIa[]];
    buscou: [provedor: ProvedorIa, modelos: string[]];
}>();

const provedor = defineModel<ProvedorIa | ''>('provedor', { required: true });
const modelo = defineModel<string>('modelo', { required: true });
const chave = defineModel<string>('chave', { required: true });

const selecionado = computed(() => props.provedores.find((item) => item.valor === provedor.value) ?? null);

const chaveVisivel = ref<boolean>(false);
const status = reactive({ acao: '' as '' | 'buscar' | 'testar', mensagem: '', erro: false });

const ajudaChave = computed((): string => {
    if (!selecionado.value) return '';

    return selecionado.value.chave
        ? `Chave guardada: ${selecionado.value.chave}. Deixe em branco para manter, ou cole outra para trocar.`
        : `${selecionado.value.ajudaChave} A chave fica cifrada e não aparece de novo nesta tela.`;
});

// Os buscados no provedor e, se não vierem na lista, o modelo salvo e o escolhido (para não sumirem do campo).
const opcoesDeModelo = computed((): { valor: string; rotulo: string }[] => {
    if (!selecionado.value) return [];

    const buscados = props.modelosBuscados[selecionado.value.valor] ?? [];
    const foraDaLista = [...new Set([selecionado.value.modelo, modelo.value])].filter((valor): valor is string => Boolean(valor) && !buscados.includes(valor as string));

    return [
        ...foraDaLista.map((valor) => ({ valor, rotulo: buscados.length > 0 ? `${valor} (fora da lista do provedor)` : valor })),
        ...buscados.map((valor) => ({ valor, rotulo: valor })),
    ];
});

const avisar = (mensagem: string, erro: boolean): void => {
    Object.assign(status, { acao: '', mensagem, erro });
};

// Lê o provedor do próprio <select>: o v-model só chega ao componente depois que o pai atualiza.
const trocarProvedor = (evento: Event): void => {
    const novo = (evento.target as HTMLSelectElement).value;
    modelo.value = props.provedores.find((item) => item.valor === novo)?.modelo ?? '';
    chave.value = '';
    chaveVisivel.value = false;
    avisar('', false);
    emit('limparErros', ['provedor', 'modelo', 'chave_api']);
};

const temChave = (): boolean => chave.value.trim() !== '' || Boolean(selecionado.value?.chave);

const buscar = async (): Promise<void> => {
    const alvo = provedor.value;
    if (alvo === '') return;
    if (!temChave()) {
        avisar('Informe a chave da API para buscar os modelos.', true);
        return;
    }

    Object.assign(status, { acao: 'buscar', mensagem: '', erro: false });
    const resposta = await buscarModelosIa({ provedor: alvo, chave_api: chave.value.trim() });
    if (resposta.ok) emit('buscou', alvo, resposta.modelos);
    // Trocou de provedor enquanto buscava: a lista fica guardada para ele, mas o aviso já não vale.
    if (provedor.value !== alvo) return;

    if (!resposta.ok) {
        avisar(resposta.mensagem, true);
    } else if (resposta.modelos.length === 0) {
        avisar('Nenhum modelo de texto disponível para esta chave.', true);
    } else {
        const total = resposta.modelos.length;
        avisar(`${total} ${total === 1 ? 'modelo encontrado' : 'modelos encontrados'}. Escolha um na lista.`, false);
    }
};

const testar = async (): Promise<void> => {
    const alvo = provedor.value;
    if (alvo === '') return;
    if (modelo.value === '') {
        avisar('Escolha o modelo antes de testar.', true);
        return;
    }
    if (!temChave()) {
        avisar('Informe a chave da API para testar a conexão.', true);
        return;
    }

    Object.assign(status, { acao: 'testar', mensagem: '', erro: false });
    const digitada = chave.value.trim();
    const resposta = await testarConexaoIa({ provedor: alvo, modelo: modelo.value, chave_api: digitada });
    if (provedor.value !== alvo) return;

    avisar(resposta.ok && digitada !== '' ? `${resposta.mensagem} A chave digitada ainda não foi salva.` : resposta.mensagem, !resposta.ok);
};

const classeBotao =
    'pressionavel inline-flex min-h-12 shrink-0 items-center justify-center gap-2 rounded-pill border border-acao bg-superficie px-6 text-[0.8125rem] font-bold tracking-[0.12em] text-acao uppercase hover:bg-nevoa disabled:cursor-wait disabled:opacity-70';
</script>

<template>
    <div class="flex flex-col gap-5">
        <CampoCadastro :id="`${id}-provedor`" rotulo="Provedor" :obrigatorio="!vazio" :erro="erros.provedor">
            <template #default="{ descricao, invalido }">
                <select
                    :id="`${id}-provedor`"
                    v-model="provedor"
                    :class="[classeCampoBase, 'w-full sm:max-w-sm']"
                    :aria-invalid="invalido || undefined"
                    :aria-describedby="descricao"
                    @change="trocarProvedor"
                >
                    <option value="" :disabled="!vazio">{{ vazio ?? 'Selecione…' }}</option>
                    <option v-for="item in provedores" :key="item.valor" :value="item.valor">{{ item.rotulo }}</option>
                </select>
            </template>
        </CampoCadastro>

        <template v-if="selecionado">
            <CampoCadastro :id="`${id}-chave`" rotulo="Chave da API" :obrigatorio="!selecionado.chave" :ajuda="ajudaChave" :erro="erros.chave_api">
                <template #default="{ descricao, invalido }">
                    <div class="relative">
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 24 24"
                            class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-tinta-suave"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M15.5 7.5a4 4 0 1 1-3.4 6.1L4 21.5V19h2.5v-2H9v-2.1l1.9-1.9A4 4 0 0 1 15.5 7.5z" />
                        </svg>
                        <!-- new-password e os data-*: o navegador e os gerenciadores de senha não preenchem aqui a senha da retaguarda. -->
                        <input
                            :id="`${id}-chave`"
                            v-model="chave"
                            :name="`${id}-chave-api`"
                            :type="chaveVisivel ? 'text' : 'password'"
                            autocomplete="new-password"
                            autocapitalize="off"
                            spellcheck="false"
                            maxlength="300"
                            data-1p-ignore
                            data-lpignore="true"
                            data-bwignore
                            data-form-type="other"
                            :placeholder="selecionado.chave ? '•••••••••• (configurada)' : 'Cole aqui a chave da API'"
                            :class="[classeCampoBase, 'w-full pr-12 pl-11']"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @input="emit('limparErros', ['chave_api'])"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 inline-flex w-12 items-center justify-center text-acao"
                            aria-label="Mostrar chave"
                            :aria-controls="`${id}-chave`"
                            :aria-pressed="chaveVisivel ? 'true' : 'false'"
                            @click="chaveVisivel = !chaveVisivel"
                        >
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                                <circle cx="12" cy="12" r="3" />
                                <path v-if="chaveVisivel" d="M3 3l18 18" />
                            </svg>
                        </button>
                    </div>
                </template>
            </CampoCadastro>

            <CampoCadastro :id="`${id}-modelo`" rotulo="Modelo" obrigatorio :erro="erros.modelo">
                <template #default="{ descricao, invalido }">
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <select
                            :id="`${id}-modelo`"
                            v-model="modelo"
                            :class="[classeCampoBase, 'w-full sm:min-w-0 sm:flex-1']"
                            :aria-invalid="invalido || undefined"
                            :aria-describedby="descricao"
                            @change="emit('limparErros', ['modelo'])"
                        >
                            <option value="" disabled>{{ opcoesDeModelo.length > 0 ? 'Selecione o modelo' : 'Use “Buscar” para listar os modelos' }}</option>
                            <option v-for="opcao in opcoesDeModelo" :key="opcao.valor" :value="opcao.valor">{{ opcao.rotulo }}</option>
                        </select>
                        <button type="button" :disabled="status.acao !== ''" :class="classeBotao" @click="buscar">
                            <svg
                                v-if="status.acao === 'buscar'"
                                aria-hidden="true"
                                viewBox="0 0 24 24"
                                class="size-4 animate-spin motion-reduce:animate-none"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                            >
                                <path d="M12 3a9 9 0 1 0 9 9" />
                            </svg>
                            <svg v-else aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6" />
                            </svg>
                            {{ status.acao === 'buscar' ? 'Buscando…' : 'Buscar' }}
                        </button>
                    </div>
                </template>
            </CampoCadastro>
        </template>

        <slot />

        <p aria-live="polite" class="text-[0.9375rem]" :class="status.erro ? 'font-semibold text-erro-texto' : 'text-tinta-suave'">{{ status.mensagem }}</p>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
            <button v-if="selecionado" type="button" :disabled="status.acao !== ''" :class="classeBotao" @click="testar">
                <svg
                    v-if="status.acao === 'testar'"
                    aria-hidden="true"
                    viewBox="0 0 24 24"
                    class="size-4 animate-spin motion-reduce:animate-none"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.2"
                    stroke-linecap="round"
                >
                    <path d="M12 3a9 9 0 1 0 9 9" />
                </svg>
                <svg v-else aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 2v5M15 2v5M6 7h12v4a6 6 0 0 1-12 0zM12 17v5" />
                </svg>
                {{ status.acao === 'testar' ? 'Testando…' : 'Testar conexão' }}
            </button>
            <slot name="acoes" />
        </div>
    </div>
</template>
