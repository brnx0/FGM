// Tipos espelhando o que o backend envia (HandleInertiaRequests e controllers).

export type SituacaoEdital = 'em_breve' | 'aberto' | 'encerrado';

export interface UsuarioAutenticado {
    nome: string;
    cpfMascarado: string;
}

/** Props compartilhadas, vindas de App\Http\Middleware\HandleInertiaRequests. */
export interface SharedProps {
    auth: { user: UsuarioAutenticado | null };
    ambienteTeste: boolean;
    flash: { erro: string | null };
    errors: Record<string, string>;
}

/** Item da vitrine em HomeController. */
export interface EditalResumo {
    titulo: string;
    slug: string;
    categoria: string;
    situacao: SituacaoEdital;
    situacaoRotulo: string;
    inscricoesInicio: string;
    inscricoesFim: string;
}

/** Edital em EditalController@show. */
export interface EditalDetalhe extends EditalResumo {
    resumo: string;
}

export interface Evento {
    titulo: string;
    resumo: string;
    dataRealizacao: string;
    local: string | null;
    imagem: string | null;
}

export interface ContaFake {
    cpf: string;
    nome: string;
    email: string | null;
}

export interface HomeProps {
    totalAbertos: number;
    editais: EditalResumo[];
    eventos: Evento[];
}

export interface EditaisShowProps {
    edital: EditalDetalhe;
}

export interface FakeLoginProps {
    contas: ContaFake[];
}

/** Cadastro/Completar não recebe props próprias, só as compartilhadas. */
export type CadastroCompletarProps = Record<string, never>;
