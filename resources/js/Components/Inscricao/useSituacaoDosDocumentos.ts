import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { Ref } from 'vue';
import type { DocumentoDaInscricao, SituacaoDaInscricao, SituacaoDoDocumento } from '../../types';

const INTERVALO_CONSULTA_MS = 5_000;

interface Entrada {
    inscricao: { id: number };
    documentos: DocumentoDaInscricao[];
    pendencias: string[];
}

interface SituacaoDosDocumentos {
    /** Situação de cada documento exigido, pelo id; nulo = nada enviado. */
    situacoes: Ref<Record<number, SituacaoDoDocumento | null>>;
    /** O que ainda impede de finalizar. */
    pendencias: Ref<string[]>;
    podeFinalizar: Ref<boolean>;
}

/**
 * Situação de cada documento enviado e o que falta para finalizar: cópia local, que a consulta periódica atualiza
 * sem recarregar a página (a cada 5 s, enquanto algum documento está conferindo). Cada recarga das props (enviar,
 * remover, trocar escolhas) recomeça a cópia.
 */
export const useSituacaoDosDocumentos = (props: Entrada): SituacaoDosDocumentos => {
    const situacoes = ref<Record<number, SituacaoDoDocumento | null>>({});
    const pendencias = ref<string[]>([]);
    const podeFinalizar = ref<boolean>(false);
    let geracao = 0;

    const sincronizar = (): void => {
        geracao += 1;
        situacoes.value = Object.fromEntries(
            props.documentos.map((documento): [number, SituacaoDoDocumento | null] => [
                documento.id,
                documento.enviado ? { situacao: documento.enviado.situacao, motivo: documento.enviado.motivo } : null,
            ]),
        );
        pendencias.value = [...props.pendencias];
        podeFinalizar.value = props.pendencias.length === 0;
    };
    sincronizar();
    watch(() => [props.documentos, props.pendencias], sincronizar);

    const temConferindo = computed<boolean>(() => Object.values(situacoes.value).some((situacao) => situacao?.situacao === 'conferindo'));

    // Uma falha de rede fica para a próxima rodada; a resposta de antes de uma recarga das props é descartada.
    const consultar = async (): Promise<void> => {
        const daGeracao = geracao;
        try {
            // Caminho da rota portal.inscricoes.situacao (o front não tem helper de rotas nomeadas).
            const resposta = await fetch(`/portal/inscricoes/${props.inscricao.id}/situacao`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!resposta.ok || daGeracao !== geracao) {
                return;
            }

            const atual = (await resposta.json()) as SituacaoDaInscricao;
            if (daGeracao !== geracao) {
                return;
            }

            for (const documento of atual.documentos) {
                situacoes.value[documento.id] = { situacao: documento.situacao, motivo: documento.motivo };
            }
            pendencias.value = atual.pendencias;
            podeFinalizar.value = atual.podeFinalizar;
        } catch {
            // Sem conexão: tenta de novo na próxima rodada.
        }
    };

    let temporizador: ReturnType<typeof setInterval> | undefined;
    const pararConsulta = (): void => {
        clearInterval(temporizador);
        temporizador = undefined;
    };
    watch(
        temConferindo,
        (conferindo) => {
            if (conferindo && temporizador === undefined) {
                temporizador = setInterval(() => void consultar(), INTERVALO_CONSULTA_MS);
            } else if (!conferindo) {
                pararConsulta();
            }
        },
        { immediate: true },
    );
    onBeforeUnmount(pararConsulta);

    return { situacoes, pendencias, podeFinalizar };
};
