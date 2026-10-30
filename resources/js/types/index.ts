// Tipos espelhando o que o backend envia (HandleInertiaRequests e controllers).

export type SituacaoEdital = 'em_breve' | 'aberto' | 'encerrado';

/** App\Enums\TipoCadastro. */
export type TipoCadastro = 'proponente' | 'patrocinador';

/** Usuário da área da página: o proponente no portal, o gestor na retaguarda (gestor no portal chega como null). */
export interface UsuarioAutenticado {
    nome: string;
    cpfMascarado: string;
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
    resumo: string;
    pdf: string | null;
    numero: string | null;
    tipoRotulo: string | null;
    objeto: string | null;
    chamadas: EditalChamadaPortal[];
    /** Rótulos das naturezas que podem se inscrever. */
    naturezas: string[];
    aceitaColetivos: boolean;
    inscricoesPorProponente: number | null;
    categorias: EditalCategoriaPortal[];
    cotas: EditalCotaPortal[];
    documentosInscricao: EditalDocumentoPortal[];
    /** Só as seções preenchidas, em texto puro com quebras de linha. */
    informacoes: EditalInformacaoPortal[];
}

export interface EditalChamadaPortal {
    nome: string;
    inicio: string | null;
    fim: string | null;
    situacao: SituacaoEdital | null;
    situacaoRotulo: string | null;
}

export interface EditalCategoriaPortal {
    nome: string;
    descricao: string | null;
    /** Já formatado ("R$ 1.500,00"). */
    valor: string | null;
    vagas: number | null;
    itens: { nome: string; valor: string | null; vagas: number | null; suplentes: number | null }[];
}

export interface EditalCotaPortal {
    modalidade: string;
    percentual: number | null;
    /** Em vagas ou, com a base "recursos", em reais. */
    quantidade: number | null;
    /** Valor de BaseCota; null quando o edital não diz (vale como vagas). */
    base: 'vagas' | 'recursos' | null;
}

export interface EditalDocumentoPortal {
    nome: string;
    orientacao: string | null;
    obrigatorio: boolean;
    /** Vazio = todas as naturezas do edital. */
    naturezas: string[];
    /** Vazio = todas as categorias. */
    categorias: string[];
    modeloUrl: string | null;
    modeloNome: string | null;
}

export interface EditalInformacaoPortal {
    chave: string;
    titulo: string;
    texto: string;
}

export interface Evento {
    titulo: string;
    /** Endereço da página: /eventos/{slug}. */
    slug: string;
    resumo: string;
    /** Dia do mês ("7"). */
    dia: string;
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

/** Evento em EventoController@show (página) e @detalhes (JSON do modal da página inicial). */
export interface EventoDetalhe {
    titulo: string;
    resumo: string;
    /** HTML já limpo ao salvar (App\Support\HtmlSeguro). */
    descricao: string | null;
    /** "15 de agosto de 2026". */
    dataRealizacao: string;
    /** "sábado". */
    diaSemana: string;
    /** "19h às 22h30", "A partir das 19h" ou nulo. */
    horario: string | null;
    local: string | null;
    endereco: string | null;
    /** Pin marcado na retaguarda; nulos quando o evento não tem mapa. */
    latitude: number | null;
    longitude: number | null;
    imagem: string | null;
    /** Endereço da página do evento. */
    url: string;
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

/** Etapa Pessoa Jurídica do cadastro (CadastroController@organizacao). */
export interface CadastroOrganizacaoProps {
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
    /** Nulo no rascunho (ex.: importação ainda incompleta). */
    titulo: string | null;
    categoria: string | null;
    /** "01/11/2026 a 30/11/2026"; nulo no rascunho sem datas. */
    periodo: string | null;
    situacao: SituacaoEdital | null;
    situacaoRotulo: string | null;
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
    /** Situação da inscrição do usuário no edital: finalizada se alguma estiver finalizada; nulo sem inscrição. */
    minhaInscricao: SituacaoInscricao | null;
}

export interface PortalEditaisProps {
    editais: Paginado<EditalPortal>;
}

/** App\Enums\SituacaoInscricao. */
export type SituacaoInscricao = 'rascunho' | 'finalizada';

/** Cadastro com que o usuário pode se inscrever (App\Support\Inscricao\PropsDaInscricao). */
export interface CadastroDaInscricao {
    /** Valor enviado no formulário: "pessoa_fisica" ou "organizacao:{id}". */
    chave: string;
    nome: string;
    /** CPF ou CNPJ já formatado. */
    documento: string;
    /** Valor de App\Enums\NaturezaProponente. */
    natureza: string;
    naturezaRotulo: string;
    /** A natureza do cadastro é uma das que o edital aceita. */
    aceito: boolean;
    /** Nenhuma chamada do edital aceita mais inscrição deste cadastro. */
    limiteAtingido: boolean;
    /** Situação da inscrição do mesmo CPF/CNPJ feita por outro usuário, que o usuário não abre. */
    inscricaoDeOutro: SituacaoInscricao | null;
}

export interface CategoriaDaInscricao {
    id: number;
    nome: string;
    /** Valores de NaturezaProponente que podem escolher a categoria; vazio vale para todas. */
    naturezas: string[];
}

export interface CotaDaInscricao {
    /** Valor de App\Enums\ModalidadeCota. */
    valor: string;
    rotulo: string;
}

/** O que o proponente escolhe ao criar a inscrição ou trocar as escolhas do rascunho. */
export interface OpcoesDaInscricao {
    cadastros: CadastroDaInscricao[];
    categorias: CategoriaDaInscricao[];
    /** Só as cotas que o edital oferece. */
    cotas: CotaDaInscricao[];
    /** Não pode marcar pessoas negras e indígenas ao mesmo tempo. */
    vedaNegroIndigena: boolean;
}

export interface InscricaoResumo {
    id: number;
    /** Nulo até a inscrição ser finalizada pela primeira vez. */
    numero: string | null;
    situacao: SituacaoInscricao;
    situacaoRotulo: string;
    /** Nome do cadastro com que a inscrição foi feita. */
    cadastro: string;
    categoria: string | null;
}

/** Tela de inscrição num edital aberto (PortalController@inscricao). */
export interface PortalInscricaoProps extends OpcoesDaInscricao {
    edital: Pick<EditalPortal, 'slug' | 'titulo' | 'categoria' | 'periodo' | 'pdf'>;
    inscricoes: InscricaoResumo[];
}

/** AppEnumsChecagemDocumento. */
export type ChecagemDocumento = 'rigorosa' | 'aceita_sem_validar' | 'nenhuma';

/** AppEnumsSituacaoConferencia; a conferência parada há mais de 5 minutos já vem como nao_conferido. */
export type SituacaoConferencia = 'enviado' | 'conferindo' | 'aprovado' | 'reprovado' | 'nao_conferido';

/** Resultado da conferência de um documento enviado; o motivo vem da IA (reprovado) ou da mensagem padrão (não conferido). */
export interface SituacaoDoDocumento {
    situacao: SituacaoConferencia;
    motivo: string | null;
}

/** Arquivo que o proponente enviou para um documento exigido. */
export interface ArquivoEnviado extends SituacaoDoDocumento {
    nome: string;
    /** Download do arquivo (rota portal.inscricoes.documentos.arquivo). */
    url: string;
}

export interface DocumentoDaInscricao {
    id: number;
    nome: string;
    orientacao: string | null;
    /** Valor de AppEnumsEtapaDocumento. */
    etapa: string;
    etapaRotulo: string;
    obrigatorio: boolean;
    modeloUrl: string | null;
    modeloNome: string | null;
    checagem: ChecagemDocumento;
    /** Nulo enquanto nada foi enviado. */
    enviado: ArquivoEnviado | null;
}

/** Tela da inscrição com a lista de documentos (InscricaoController@show). */
export interface PortalInscricaoDocumentosProps extends OpcoesDaInscricao {
    edital: Pick<EditalPortal, 'slug' | 'titulo' | 'categoria' | 'periodo' | 'pdf'>;
    inscricao: InscricaoResumo & {
        /** Valor de CadastroDaInscricao.chave do cadastro da inscrição. */
        cadastroChave: string;
        categoriaId: number | null;
        /** Valores de ModalidadeCota marcados. */
        cotas: string[];
        /** Último dia da chamada da inscrição ("15/11/2026"). */
        fimDoPeriodo: string | null;
        /** Data e hora do envio ("12/11/2026 15:30"); nulo no rascunho. */
        finalizadaEm: string | null;
    };
    /** Rascunho dentro do período da chamada: só então as escolhas e os documentos podem ser trocados. */
    podeEditar: boolean;
    /** Finalizada e dentro do período da chamada. */
    podeReabrir: boolean;
    /** O que ainda impede de finalizar (vazio = pode finalizar); a consulta JSON de situação traz a lista atualizada e podeFinalizar. */
    pendencias: string[];
    /** Os exigidos para as escolhas da inscrição, de todas as etapas, na ordem do edital. */
    documentos: DocumentoDaInscricao[];
}

/** Consulta periódica enquanto há documento conferindo (InscricaoController@situacao). */
export interface SituacaoDaInscricao {
    /** Um por documento enviado; id é o do documento exigido (DocumentoDaInscricao.id). */
    documentos: (SituacaoDoDocumento & { id: number })[];
    podeFinalizar: boolean;
    pendencias: string[];
}

export interface RetaguardaEditaisIndexProps {
    editais: Paginado<EditalRetaguarda>;
    filtros: { busca: string; situacao: SituacaoEdital | ''; publicado: 'sim' | 'nao' | '' };
    situacoes: { valor: SituacaoEdital; rotulo: string }[];
    /** Provedor principal com modelo e chave na Integração com IA. "Importar com IA" aparece sempre; sem isso, o envio é recusado. */
    importacaoConfigurada: boolean;
    /** Tamanho máximo do PDF da importação (Integração com IA). */
    tamanhoMaximoPdfMb: number;
    importacoes: ImportacaoResumo[];
}

/** Situação de uma importação para a tela (análise sem término há mais de 10 min já vem como "erro"). */
export type SituacaoImportacao = 'analisando' | 'concluida' | 'erro';

/** Importação no painel da lista de editais (EditalController@index); a consulta JSON devolve situacao, erro e editalUrl. */
export interface ImportacaoResumo {
    id: number;
    pdfNome: string;
    situacao: SituacaoImportacao;
    erro: string | null;
    editalUrl: string | null;
    /** dd/mm/aaaa hh:mm */
    enviadaEm: string;
}

/** Ponto de atenção da importação; bloco nulo = sem bloco (rótulo "Geral"). */
export interface PontoDeAtencao {
    bloco: string | null;
    blocoRotulo: string;
    tipo: 'nao_encontrado' | 'divergencia' | 'incerta';
    tipoRotulo: string;
    texto: string;
    itens: string[];
}

/** Quadro "Importado pela IA" do formulário do edital, enquanto a importação não foi conferida. */
export interface ImportacaoDoEdital {
    confiabilidade: {
        geral: number | null;
        /** Na ordem das abas, só os blocos que receberam nota (0 a 100). */
        blocos: { bloco: string; rotulo: string; nota: number; justificativa: string }[];
    };
    atencao: PontoDeAtencao[];
    conferida: boolean;
    modelo: string;
    tokensTotal: number | null;
    /** dd/mm/aaaa hh:mm */
    importadaEm: string;
}

/** Opção de um campo de escolha (valor do enum e rótulo). */
export interface OpcaoFormulario {
    valor: string;
    rotulo: string;
}

export interface CotaModalidadeFormulario {
    ativa: boolean;
    percentual: number | null;
    quantidade: number | null;
}

/** Cotas do edital; chaves das modalidades = valores de ModalidadeCota. */
export interface CotasFormulario {
    negros: CotaModalidadeFormulario;
    indigenas: CotaModalidadeFormulario;
    pcd: CotaModalidadeFormulario;
    base: string | null;
    alcance: string | null;
    veda_negro_indigena: boolean | null;
    heteroidentificacao: boolean | null;
}

export interface PontuacaoExtraFormulario {
    grupos: { grupo: string; pontos_por_pessoa: number | null; maximo: number | null }[];
    teto: number | null;
    conta_para_corte: boolean | null;
}

/** Listas do edital: `id` do registro salvo; referências entre listas são posições (índice a partir de 0). */
export interface ChamadaFormulario {
    id: number;
    nome: string;
    inicio: string | null;
    fim: string | null;
}

export interface CategoriaItemFormulario {
    id: number;
    nome: string;
    /** Em reais. */
    valor: number | null;
    vagas: number | null;
    suplentes: number | null;
}

export interface CategoriaFormulario {
    id: number;
    nome: string;
    descricao: string | null;
    /** Em reais. */
    valor: number | null;
    vagas: number | null;
    /** Vazia = as naturezas do edital. */
    naturezas: string[];
    /** Posição da chamada. */
    chamada: number | null;
    itens: CategoriaItemFormulario[];
}

export interface DocumentoFormulario {
    id: number;
    nome: string;
    orientacao: string | null;
    etapa: string;
    obrigatorio: boolean;
    /** Vazia = todas as naturezas. */
    naturezas: string[];
    todas_categorias: boolean;
    /** Posições das categorias. */
    categorias: number[];
    modalidade_cota: string | null;
    checagem: string;
    instrucao_ia: string | null;
    modeloUrl: string | null;
    modeloNome: string | null;
}

export interface RequisitoFormulario {
    id: number;
    descricao: string;
    /** Posições dos documentos. */
    documentos: number[];
}

export interface CriterioFormulario {
    id: number;
    nome: string;
    descricao: string | null;
    peso: number | null;
    pontos_maximos: number | null;
    todas_categorias: boolean;
    /** Posições das categorias. */
    categorias: number[];
}

/**
 * Listas no formulário do edital (Retaguarda/Editais/Form): cada item tem uma `chave` estável, só da tela, e as
 * referências entre listas guardam a chave do item referido; as posições são calculadas no envio. Decimais ficam
 * como texto digitado ("12,5") até o envio.
 */
export interface ItemEditavel {
    chave: string;
}

export interface ChamadaEditavel extends ItemEditavel {
    id: number | null;
    nome: string;
    inicio: string;
    fim: string;
}

export interface CategoriaItemEditavel extends ItemEditavel {
    id: number | null;
    nome: string;
    valor: number | null;
    vagas: number | null;
    suplentes: number | null;
}

export interface CategoriaEditavel extends ItemEditavel {
    id: number | null;
    nome: string;
    descricao: string;
    valor: number | null;
    vagas: number | null;
    naturezas: string[];
    /** Chave da chamada. */
    chamada: string | null;
    itens: CategoriaItemEditavel[];
}

export interface DocumentoEditavel extends ItemEditavel {
    id: number | null;
    nome: string;
    orientacao: string;
    etapa: string;
    obrigatorio: boolean;
    naturezas: string[];
    todas_categorias: boolean;
    /** Chaves das categorias. */
    categorias: string[];
    modalidade_cota: string;
    checagem: string;
    instrucao_ia: string;
    /** Arquivo-modelo novo (só vai quando escolhido). */
    modelo: File | null;
    remover_modelo: boolean;
    modeloUrl: string | null;
    modeloNome: string | null;
}

export interface RequisitoEditavel extends ItemEditavel {
    id: number | null;
    descricao: string;
    /** Chaves dos documentos. */
    documentos: string[];
}

export interface CriterioEditavel extends ItemEditavel {
    id: number | null;
    nome: string;
    descricao: string;
    peso: string;
    pontos_maximos: string;
    todas_categorias: boolean;
    /** Chaves das categorias. */
    categorias: string[];
}

export interface GrupoPontuacaoEditavel extends ItemEditavel {
    grupo: string;
    pontos_por_pessoa: string;
    maximo: string;
}

export interface PontuacaoExtraEditavel {
    grupos: GrupoPontuacaoEditavel[];
    teto: string;
    conta_para_corte: boolean | null;
}

export interface CotaModalidadeEditavel {
    ativa: boolean;
    percentual: string;
    quantidade: string;
}

export interface CotasEditavel {
    /** Pela modalidade (valor de ModalidadeCota). */
    modalidades: Record<string, CotaModalidadeEditavel>;
    base: string;
    alcance: string;
    veda_negro_indigena: boolean | null;
    heteroidentificacao: boolean | null;
}

/** Edital em edição (Retaguarda\EditalController@edit). Datas em Y-m-d; rascunho pode vir sem título, textos e datas. */
export interface EditalFormulario {
    slug: string;
    titulo: string | null;
    resumo: string | null;
    categoria: string | null;
    /** Calculadas das chamadas ao salvar. */
    inscricoes_inicio: string | null;
    inscricoes_fim: string | null;
    publicado: boolean;
    /** Download do PDF atual pela retaguarda (vale também para rascunho). */
    pdfUrl: string | null;
    pdfNome: string | null;
    imagemUrl: string | null;
    numero: string | null;
    tipo: string | null;
    objeto: string | null;
    dom_data: string | null;
    dom_numero: string | null;
    naturezas: string[];
    aceita_coletivos: boolean | null;
    inscricoes_por_proponente: number | null;
    limite_por_chamada: boolean | null;
    forma_inscricao_original: string | null;
    nota_maxima: number | null;
    nota_corte: number | null;
    cotas: CotasFormulario | null;
    pontuacao_extra: PontuacaoExtraFormulario | null;
    /** Todas as seções informativas (chaves de `secoesInformativas`), nulas quando vazias. */
    informacoes: Record<string, string | null>;
    chamadas: ChamadaFormulario[];
    categorias: CategoriaFormulario[];
    documentos: DocumentoFormulario[];
    requisitos: RequisitoFormulario[];
    criterios: CriterioFormulario[];
}

export interface RetaguardaEditaisFormProps {
    edital: EditalFormulario | null;
    /** O que falta para publicar (vazia na criação). */
    pendencias: string[];
    /** Importação por IA que gerou o edital, enquanto não foi conferida. */
    importacao: ImportacaoDoEdital | null;
    naturezas: OpcaoFormulario[];
    etapas: OpcaoFormulario[];
    modalidadesCota: OpcaoFormulario[];
    checagensDocumento: OpcaoFormulario[];
    basesCota: OpcaoFormulario[];
    alcancesCota: OpcaoFormulario[];
    tiposEdital: OpcaoFormulario[];
    secoesInformativas: OpcaoFormulario[];
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
    /** "HH:MM". */
    hora_inicio: string | null;
    hora_fim: string | null;
    local: string | null;
    endereco: string | null;
    /** Pin do mapa; nulos sem marcação. */
    latitude: number | null;
    longitude: number | null;
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

/** Linha da lista de proponentes (cadastros do portal) na retaguarda (Retaguarda\ProponenteController@index). CPF já mascarado. */
export interface ProponenteRetaguarda {
    id: number;
    nome: string;
    nomeSocial: string | null;
    cpf: string;
    email: string | null;
    /** "Cidade/UF". */
    cidade: string | null;
    /** Rótulos dos tipos de cadastro; vazio enquanto o cadastro está em andamento. */
    tipos: string[];
    finalizado: boolean;
    cadastradoEm: string | null;
}

export interface RetaguardaProponentesIndexProps {
    proponentes: Paginado<ProponenteRetaguarda>;
    filtros: { busca: string; situacao: string; tipo: string };
    situacoes: { valor: string; rotulo: string }[];
    tiposCadastro: { valor: string; rotulo: string }[];
}

/** Bloco do detalhe do proponente, com os valores já formatados (nulo quando não informado). */
export interface SecaoProponente {
    id: string;
    titulo: string;
    campos: { rotulo: string; valor: string | null }[];
}

/** Anexo do cadastro: a URL é a da rota da retaguarda, nunca o caminho no disco. */
export interface AnexoProponente {
    campo: string;
    rotulo: string;
    enviado: boolean;
    imagem: boolean;
    url: string | null;
    /** Nome sugerido para baixar. */
    nome: string | null;
}

/** Pessoa Jurídica vinculada ao CPF do proponente. CPF dos representantes já mascarado. */
export interface OrganizacaoDoProponente {
    id: number;
    razaoSocial: string;
    nomeFantasia: string | null;
    cnpj: string;
    porte: string | null;
    cidade: string | null;
    responsavel: boolean;
    representantes: { nome: string; cargo: string | null; cpf: string }[];
}

export interface RetaguardaProponenteShowProps {
    proponente: {
        id: number;
        nome: string;
        tipos: string[];
        finalizado: boolean;
        cadastradoEm: string | null;
        ultimoAcesso: string | null;
    };
    secoes: SecaoProponente[];
    anexos: AnexoProponente[];
    organizacoes: OrganizacaoDoProponente[];
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

/** Linha da lista de Prefeituras-Bairro na retaguarda (Retaguarda\PrefeituraBairroController@index). */
export interface PrefeituraBairroRetaguarda {
    id: number;
    /** Em algarismos romanos ("IV"). */
    numero: string;
    nome: string;
    totalBairros: number;
    ativo: boolean;
}

export interface RetaguardaPrefeiturasBairroIndexProps {
    prefeituras: Paginado<PrefeituraBairroRetaguarda>;
    filtros: { busca: string };
}

/** Prefeitura-Bairro em edição, com os bairros em ordem alfabética (Retaguarda\PrefeituraBairroController@edit). */
export interface PrefeituraBairroFormulario {
    id: number;
    numero: number;
    nome: string;
    ativo: boolean;
    bairros: { id: number; nome: string }[];
}

export interface RetaguardaPrefeiturasBairroFormProps {
    prefeitura: PrefeituraBairroFormulario | null;
}

/** Troca da própria senha (Retaguarda\MinhaSenhaController@edit). Sem senha (só gov.br), a atual não é pedida. */
/** Uma aba de AbasFormulario (formulários longos da retaguarda). */
export interface AbaFormulario {
    id: string;
    rotulo: string;
    /** Campos do formulário desta aba: os erros deles contam no marcador da aba. */
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

/** App\Enums\ProvedorIa. */
export type ProvedorIa = 'openai' | 'gemini';

/** Provedor na Integração com IA. A chave em si nunca vem para o navegador: só o começo e o fim dela. */
export interface ProvedorIaRetaguarda {
    valor: ProvedorIa;
    rotulo: string;
    /** Onde criar a chave; aparece enquanto o provedor não tem uma guardada. */
    ajudaChave: string;
    modelo: string | null;
    /** Máscara da chave guardada (ex.: "sk-…H2UA"); null sem chave. */
    chave: string | null;
}

/** Integração com IA: o principal, a redundância e um item por provedor (Retaguarda\IntegracaoIaController@edit). */
export interface RetaguardaIntegracaoIaProps {
    configuracao: { ativo: boolean; principal: ProvedorIa | null; redundancia: ProvedorIa | null };
    provedores: ProvedorIaRetaguarda[];
    importacao: ImportacaoIaRetaguarda;
}

/** Parâmetros da importação de edital (cartão 3 da Integração com IA); nada da conexão. */
export interface ImportacaoIaRetaguarda {
    /** O prompt da equipe ou, sem ele, o prompt modelo. */
    prompt: string;
    promptModelo: string;
    /** De 0 a 1. */
    temperatura: number;
    /** De 1 a 14. */
    tamanhoMaximoMb: number;
    /** Há provedor principal com modelo e chave: a importação pode ser usada. */
    configurada: boolean;
}
