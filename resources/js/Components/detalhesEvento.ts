import { consultar } from './Organizacao/mascaras';
import type { EventoDetalhe } from '../types';

// Detalhes por slug (EventoController@detalhes). A promessa fica guardada: reabrir o modal, ou abrir
// depois do adiantamento (mouse ou foco no card), não repete a consulta. Falha sai do cache.
const pedidos = new Map<string, Promise<EventoDetalhe | null>>();

export const carregarDetalhesDoEvento = (slug: string): Promise<EventoDetalhe | null> => {
    const guardado = pedidos.get(slug);
    if (guardado) return guardado;

    const pedido = consultar<EventoDetalhe>(`/eventos/${encodeURIComponent(slug)}/detalhes`).then((detalhe) => {
        if (detalhe === null) pedidos.delete(slug);
        return detalhe;
    });
    pedidos.set(slug, pedido);

    return pedido;
};
