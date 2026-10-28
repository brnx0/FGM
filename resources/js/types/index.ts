// Tipos espelhando o que o backend envia (HandleInertiaRequests e controllers).

export type SituacaoEdital = 'em_breve' | 'aberto' | 'encerrado';

/** App\Enums\TipoCadastro. */
export type TipoCadastro = 'proponente' | 'patrocinador';

export interface UsuarioAutenticado {
    nome: string;
    cpfMascarado: string;
    /** Ativo e com pelo menos um papel: pode entrar na retaguarda. */
    ehGestor: boolean;
    /** Vazio até a última etapa do cadastro; define o menu do portal (um tipo ou os dois). */
    tiposCadastro: TipoCadastro[];
}

/** Props compartilhadas, vindas de App\Http\Middleware\HandleInertiaRequests. */
export interface SharedProps {
    auth: { user: UsuarioAutenticado | null };
    ambienteTeste: boolean;
    flash: { erro: string | null; aviso: string | null; sucesso: string | null };
    errors: Record<string, string>;
}

/** Item da vitrine em HomeController. */
export interface EditalResumo {
    titulo: string;
    slug: string;
    /** URL do PDF do edital (rota editais.pdf). */
    pdf: string;
    categoria: string;
    situacao: SituacaoEdital;
    situacaoRotulo: string;
    inscricoesInicio: string;
    inscricoesFim: string;
    /** Imagem enviada na retaguarda (URL relativa); null usa as fotos fixas da vitrine. */
    imagem: string | null;
}

/** Edital em EditalController@show. */
export interface EditalDetalhe extends Omit<EditalResumo, 'pdf' | 'imagem'> {
    /** HTML já limpo ao salvar (App\Support\HtmlSeguro). */
    divulgacao: string | null;
    pdf: string | null;
}

export interface Evento {
    titulo: string;
    /** Endereço da página: /eventos/{slug}. */
    slug: string;
    resumo: string;
    /** Nome do mês em minúsculas ("agosto"). */
    mes: string;
    ano: string;
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

/** Evento em EventoController@show (portal). */
export interface EventoDetalhe {
    titulo: string;
    resumo: string;
    /** HTML já limpo ao salvar (App\Support\HtmlSeguro). */
    descricao: string | null;
    /** "15 de agosto de 2026". */
    dataRealizacao: string;
    local: string | null;
    imagem: string | null;
}

export interface EventosShowProps {
    evento: EventoDetalhe;
}

export interface FakeLoginProps {
    contas: ContaFake[];
}

export interface OpcaoCadastro {
    valor: string;
    rotulo: string;
}

/** Dados que vêm do gov.br: aparecem no formulário sem poder ser alterados. */
export interface DadosGovBr {
    nome: string;
    /** Formatado: 529.982.247-25. */
    cpf: string;
    email: string | null;
    /** Só os números, com DDD. */
    telefone: string | null;
}

export type ArquivoCadastro =
    | 'foto_perfil'
    | 'documento_cpf'
    | 'documento_rg'
    | 'documento_passaporte'
    | 'documento_crnm'
    | 'documento_outros'
    | 'comprovante_residencia';

/** Cadastro de pessoa física já salvo. Dos arquivos, só se cada um já foi enviado. */
export interface CadastroPessoaFisica {
    nome_social: string | null;
    nome_artistico: string | null;
    pais_origem: string;
    rg: string;
    drt: string | null;
    cultura_principal_renda: boolean;
    ja_foi_avaliador: boolean;
    interesse_avaliador: boolean;
    representa_coletivo: boolean;
    telefone: string;
    telefone_adicional: string | null;
    email: string;
    identidade_genero: string;
    raca_cor: string;
    pcd: boolean;
    cep: string;
    logradouro: string;
    numero: string | null;
    bairro: string;
    complemento: string | null;
    cidade: string;
    estado: string;
    territorio: string;
    arquivos: Record<ArquivoCadastro, boolean>;
}

/** Cadastro/Completar: o formulário de pessoa física, em CadastroController@completar. */
export interface CadastroCompletarProps {
    govbr: DadosGovBr;
    cadastro: CadastroPessoaFisica | null;
    opcoes: {
        paises: string[];
        estados: OpcaoCadastro[];
        identidadesGenero: OpcaoCadastro[];
        racasCores: OpcaoCadastro[];
        territorios: OpcaoCadastro[];
    };
}

/** Item da lista de organizações da pessoa. */
export interface OrganizacaoResumo {
    id: number;
    nome: string;
    cnpj: string;
    logoUrl: string | null;
    responsavel: boolean;
}

export interface OrganizacoesIndexProps {
    organizacoes: OrganizacaoResumo[];
}

/** Pessoa vinculada a uma organização (CPF mascarado). */
export interface VinculoPessoa {
    id: number;
    nome: string;
    cpf: string;
    responsavel: boolean;
    ehVoce: boolean;
}

export interface OrganizacaoVinculosProps {
    organizacao: { id: number; nome: string };
    podeGerenciar: boolean;
    vinculos: VinculoPessoa[];
}

export interface RepresentanteForm {
    cpf: string;
    nome: string;
    tipo_documento: string | null;
    numero_documento: string | null;
    cargo: string | null;
    telefone: string | null;
    email: string | null;
    origem: string | null;
    origem_descricao: string | null;
}

/** Organização como o formulário de edição a recebe (CNPJ já formatado, data em aaaa-mm-dd). */
export interface OrganizacaoForm {
    id: number;
    cnpj: string;
    razao_social: string;
    nome_fantasia: string | null;
    natureza_juridica: string;
    mei: boolean;
    finalidade_lucrativa: boolean | null;
    municipio_ibge: string;
    municipio: { codigo: string; nome: string; uf: string } | null;
    inscricao_municipal: string | null;
    inscricao_estadual: string | null;
    porte: string | null;
    data_fundacao: string | null;
    telefone: string | null;
    logoUrl: string | null;
    cep: string | null;
    logradouro: string | null;
    numero: string | null;
    sem_numero: boolean;
    complemento: string | null;
    bairro: string | null;
    cidade: string | null;
    uf: string | null;
    representantes: RepresentanteForm[];
}

export interface OrganizacaoFormProps {
    organizacao: OrganizacaoForm | null;
    naturezas: OpcaoCadastro[];
    opcoes: {
        portes: OpcaoCadastro[];
        tiposDocumento: OpcaoCadastro[];
        origens: OpcaoCadastro[];
        estados: OpcaoCadastro[];
    };
    representanteInicial: { cpf: string; nome: string } | null;
    /** Verdadeiro só quando a pessoa ainda não tem organização: o formulário é a etapa 3 do cadastro inicial. */
    noCadastroInicial: boolean;
}

/** Representante como o formulário o edita: tudo texto, vazio quando não informado; CPF e telefone com máscara. */
export interface RepresentanteEdicao {
    cpf: string;
    nome: string;
    tipo_documento: string;
    numero_documento: string;
    cargo: string;
    telefone: string;
    email: string;
    origem: string;
    origem_descricao: string;
}

/** Endereço da sede como o formulário o edita (CEP com máscara). */
export interface EnderecoSede {
    cep: string;
    logradouro: string;
    numero: string;
    sem_numero: boolean;
    complemento: string;
    bairro: string;
    cidade: string;
    uf: string;
}

/** Município da lista com busca (/cadastro/consultas/municipios). */
export interface MunicipioBusca {
    codigo: string;
    nome: string;
    uf: string;
}

/** Dados públicos do CNPJ (/cadastro/consultas/cnpj/{cnpj}). Telefone e CEP só com números, data em aaaa-mm-dd. */
export interface DadosPublicosCnpj {
    razao_social: string | null;
    nome_fantasia: string | null;
    natureza_juridica: string | null;
    finalidade_lucrativa: boolean | null;
    mei: boolean;
    porte: string | null;
    data_fundacao: string | null;
    telefone: string | null;
    cep: string | null;
    logradouro: string | null;
    numero: string | null;
    sem_numero: boolean;
    complemento: string | null;
    bairro: string | null;
    cidade: string | null;
    uf: string | null;
    municipio: MunicipioBusca | null;
}

export type RespostaConsultaCnpj = { jaCadastrado: true } | { encontrado: false } | { encontrado: true; dados: DadosPublicosCnpj };

export type RespostaConsultaCep =
    | { encontrado: false }
    | { encontrado: true; dados: { logradouro: string | null; bairro: string | null; cidade: string | null; uf: string | null } };

/** Link de paginação do LengthAwarePaginator do Laravel. */
export interface LinkPaginacao {
    url: string | null;
    label: string;
    active: boolean;
}

/** LengthAwarePaginator serializado (só o que as telas usam). */
export interface Paginado<T> {
    data: T[];
    links: LinkPaginacao[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

/** Linha da lista de editais na retaguarda (Retaguarda\EditalController@index). */
export interface EditalRetaguarda {
    slug: string;
    titulo: string;
    categoria: string;
    /** "01/11/2026 a 30/11/2026". */
    periodo: string;
    situacao: SituacaoEdital;
    situacaoRotulo: string;
    publicado: boolean;
    temPdf: boolean;
}

/** Etapa 4 do cadastro (CadastroController@tipo). */
export interface CadastroTipoProps {
    tiposAtuais: TipoCadastro[];
    opcoes: { valor: TipoCadastro; rotulo: string }[];
}

/** Edital na tela Editais do portal (PortalController@editais). */
export interface EditalPortal {
    slug: string;
    titulo: string;
    categoria: string;
    periodo: string;
    situacao: SituacaoEdital;
    situacaoRotulo: string;
    /** URL do PDF do edital (rota editais.pdf). */
    pdf: string;
    /** Foto enviada na retaguarda; sem ela, o card usa uma foto da vitrine. */
    imagem: string | null;
}

export interface PortalEditaisProps {
    editais: Paginado<EditalPortal>;
}

/** Tela de inscrição num edital aberto (PortalController@inscricao). */
export interface PortalInscricaoProps {
    edital: Pick<EditalPortal, 'titulo' | 'categoria' | 'periodo' | 'pdf'>;
}

export interface RetaguardaEditaisIndexProps {
    editais: Paginado<EditalRetaguarda>;
    filtros: { busca: string; situacao: SituacaoEdital | ''; publicado: 'sim' | 'nao' | '' };
    situacoes: { valor: SituacaoEdital; rotulo: string }[];
}

/** Edital em edição (Retaguarda\EditalController@edit). Datas em Y-m-d. */
export interface EditalFormulario {
    slug: string;
    titulo: string;
    categoria: string;
    /** Valor do enum TipoFomento; null em edital antigo. */
    tipo_fomento: string | null;
    divulgacao: string | null;
    inscricoes_inicio: string;
    inscricoes_fim: string;
    vigencia_inicio: string | null;
    vigencia_fim: string | null;
    publicado: boolean;
    /** Download do PDF atual pela retaguarda (vale também para rascunho). */
    pdfUrl: string | null;
    pdfNome: string | null;
    imagemUrl: string | null;
    /** Na ordem de exibição. */
    categorias: CategoriaEdital[];
    /** Na ordem de exibição. */
    documentos: DocumentoEdital[];
}

/** App\Models\EditalCategoria::COTAS. */
export type CotaEdital = 'negros' | 'indigenas' | 'pcd';

/** App\Enums\UnidadeCota. */
export type UnidadeCota = 'vagas' | 'percentual';

/** Categoria de inscrição do edital (Retaguarda\EditalController@edit). */
export interface CategoriaEdital {
    id: number;
    nome: string;
    /** Em reais (150000.5 = R$ 150.000,50). */
    valor: number;
    vagas: number;
    /** Valores do enum NaturezaProponente. */
    naturezas: string[];
    /** Sem cota: valor e unidade null. */
    cotas: Record<CotaEdital, { valor: number | null; unidade: UnidadeCota | null }>;
}

/** Categoria no formulário do edital: `chave` identifica o cartão (não vai ao servidor); `id` só nas já salvas. */
export interface CategoriaEditavel {
    chave: string;
    id: number | null;
    nome: string;
    valor: number | null;
    vagas: number | null;
    naturezas: string[];
    cotas: Record<CotaEdital, { valor: number | null; unidade: UnidadeCota }>;
}

/** Documento exigido na inscrição (Retaguarda\EditalController@edit). */
export interface DocumentoEdital {
    id: number;
    nome: string;
    orientacao: string | null;
    obrigatorio: boolean;
    /** Valores do enum NaturezaProponente. */
    naturezas: string[];
    todas_categorias: boolean;
    /** Posições (a partir de 0) na lista `categorias` do mesmo edital. */
    categorias: number[];
    tamanho_max_mb: number;
}

/**
 * Documento no formulário do edital: `chave` identifica o cartão (não vai ao servidor); `id` só nos já salvos.
 * `categorias` guarda as chaves dos cartões das categorias: viram posições só no envio, então reordenar não desfaz a ligação.
 */
export interface DocumentoEditavel {
    chave: string;
    id: number | null;
    nome: string;
    orientacao: string;
    obrigatorio: boolean;
    naturezas: string[];
    todas_categorias: boolean;
    categorias: string[];
    tamanho_max_mb: number | null;
}

export interface RetaguardaEditaisFormProps {
    edital: EditalFormulario | null;
    tiposFomento: { valor: string; rotulo: string }[];
    /** NaturezaProponente: quem pode concorrer a cada categoria. */
    naturezas: { valor: string; rotulo: string }[];
}

/** Linha da lista de eventos na retaguarda (Retaguarda\EventoController@index). */
export interface EventoRetaguarda {
    slug: string;
    titulo: string;
    local: string | null;
    /** "15/08/2026". */
    data: string;
    publicado: boolean;
    temImagem: boolean;
}

export interface RetaguardaEventosIndexProps {
    eventos: Paginado<EventoRetaguarda>;
    filtros: { busca: string; publicado: 'sim' | 'nao' | '' };
}

/** Evento em edição (Retaguarda\EventoController@edit). Data em Y-m-d. */
export interface EventoFormulario {
    slug: string;
    titulo: string;
    resumo: string;
    /** HTML limpo; null sem descrição. */
    descricao: string | null;
    data_realizacao: string;
    local: string | null;
    publicado: boolean;
    imagemUrl: string | null;
}

export interface RetaguardaEventosFormProps {
    evento: EventoFormulario | null;
}

/** Linha da lista de usuários na retaguarda (Retaguarda\UsuarioController@index). CPF já mascarado. */
export interface UsuarioRetaguarda {
    id: number;
    nome: string;
    email: string | null;
    cpf: string;
    papeis: string[];
    ativo: boolean;
    ehVoce: boolean;
}

export interface RetaguardaUsuariosIndexProps {
    usuarios: Paginado<UsuarioRetaguarda>;
    filtros: { busca: string };
}

/** Papel que pode ser atribuído a um usuário. */
export interface PapelDisponivel {
    slug: string;
    nome: string;
}

/** Usuário em edição (Retaguarda\UsuarioController@edit). CPF formatado, ou vazio se o salvo é inválido. */
export interface UsuarioFormulario {
    id: number;
    name: string;
    email: string | null;
    cpf: string;
    papeis: string[];
    ativo: boolean;
}

export interface RetaguardaUsuariosFormProps {
    usuario: UsuarioFormulario | null;
    papeisDisponiveis: PapelDisponivel[];
}

/** Troca da própria senha (Retaguarda\MinhaSenhaController@edit). Sem senha (só gov.br), a atual não é pedida. */
/** Uma aba de AbasFormulario (formulários longos da retaguarda). */
export interface AbaFormulario {
    id: string;
    rotulo: string;
    /** Campos do formulário desta aba: os erros deles contam no marcador da aba. Terminado em ".*", vale para todo campo com esse prefixo. */
    campos: string[];
}

export interface RetaguardaMinhaSenhaProps {
    temSenha: boolean;
}

export interface RetaguardaPerfilProps {
    perfil: {
        name: string;
        email: string;
        /** Só os números, com DDD. */
        telefone: string | null;
        /** Mascarado: ***.982.247-**. */
        cpf: string;
        papeis: string[];
    };
    temSenha: boolean;
}

/** App\Enums\LadoVLibras (parâmetro position do plugin). */
export type LadoVLibras = 'R' | 'L';

/** App\Enums\AvatarVLibras (parâmetro avatar do plugin). */
export type AvatarVLibras = 'icaro' | 'hosana' | 'guga' | 'random';

/** Parâmetros do VLibras fora da retaguarda (Retaguarda\VLibrasController@edit). */
export interface RetaguardaVLibrasProps {
    configuracao: { ativo: boolean; lado: LadoVLibras; avatar: AvatarVLibras };
    lados: { valor: LadoVLibras; rotulo: string }[];
    avatares: { valor: AvatarVLibras; rotulo: string }[];
}
