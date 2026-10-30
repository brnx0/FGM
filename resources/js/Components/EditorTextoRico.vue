<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { Color, TextStyle } from '@tiptap/extension-text-style';
import Highlight from '@tiptap/extension-highlight';
import TextAlign from '@tiptap/extension-text-align';
import { TaskList } from '@tiptap/extension-task-list';
import { TaskItem } from '@tiptap/extension-task-item';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import Image from '@tiptap/extension-image';
import Youtube from '@tiptap/extension-youtube';
import { Placeholder } from '@tiptap/extension-placeholder';
import AvisoCamposObrigatorios from './AvisoCamposObrigatorios.vue';
import { focarPrimeiroErro } from './focarPrimeiroErro';

/**
 * Editor de texto rico (Tiptap) com v-model em HTML. O servidor limpa o HTML ao salvar
 * (App\Support\HtmlSeguro): o que o editor permite aqui é o mesmo que a allowlist aceita.
 */
const props = defineProps<{
    id: string;
    /** Nome acessível da área de edição. */
    rotulo: string;
    /** Ids da ajuda e do erro (aria-describedby), vindos de CampoCadastro. */
    descricao?: string;
    invalido?: boolean;
    /** Rota que recebe a imagem e devolve { url }. */
    urlImagem: string;
}>();

const modelo = defineModel<string>({ required: true });

type Alinhamento = 'left' | 'center' | 'right' | 'justify';
type NivelTitulo = 2 | 3 | 4;
type Modo = 'link' | 'imagem' | 'video';
type CampoDoDialogo = 'endereco' | 'arquivo' | 'alt';

/** Mesma paleta de HtmlSeguro::CORES. */
const CORES: { valor: string; nome: string }[] = [
    { valor: '#1d1d1f', nome: 'Grafite' },
    { valor: '#004d9f', nome: 'Azul' },
    { valor: '#0a2747', nome: 'Azul-marinho' },
    { valor: '#8a1c1c', nome: 'Vermelho' },
    { valor: '#166534', nome: 'Verde' },
    { valor: '#805b00', nome: 'Amarelo escuro' },
];

const LIMITE_IMAGEM = 5 * 1024 * 1024;

const atributosDaArea = (): Record<string, string> => ({
    id: props.id,
    class: 'conteudo-rico',
    role: 'textbox',
    'aria-multiline': 'true',
    'aria-label': props.rotulo,
    ...(props.descricao ? { 'aria-describedby': props.descricao } : {}),
    ...(props.invalido ? { 'aria-invalid': 'true' } : {}),
});

const editor = useEditor({
    content: modelo.value,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
            code: false,
            codeBlock: false,
            link: {
                openOnClick: false,
                autolink: true,
                defaultProtocol: 'https',
                protocols: ['mailto'],
                isAllowedUri: (url) => /^(https?:\/\/|mailto:)/i.test(url),
            },
        }),
        TextStyle,
        Color,
        Highlight,
        TextAlign.configure({ types: ['heading', 'paragraph'], alignments: ['left', 'center', 'right', 'justify'] }),
        TaskList,
        TaskItem.configure({
            nested: true,
            a11y: { checkboxLabel: (node, marcado) => `${marcado ? 'Concluída' : 'Pendente'}: ${node.textContent || 'tarefa vazia'}` },
        }),
        Table.configure({ resizable: false }),
        TableRow,
        TableHeader,
        TableCell,
        Image.configure({ allowBase64: false }),
        Youtube.configure({ nocookie: true, allowFullscreen: true, width: 640, height: 360 }),
        Placeholder.configure({ placeholder: 'Programação, convidados, como participar…' }),
    ],
    editorProps: { attributes: atributosDaArea() },
    onUpdate: ({ editor: atual }) => {
        modelo.value = atual.isEmpty ? '' : atual.getHTML();
    },
});

watch(
    () => [props.descricao, props.invalido],
    () => editor.value?.setOptions({ editorProps: { attributes: atributosDaArea() } }),
);

onBeforeUnmount(() => editor.value?.destroy());

const ativo = (nome: string, atributos?: Record<string, unknown>): boolean => editor.value?.isActive(nome, atributos) ?? false;
const alinhado = (lado: Alinhamento): boolean => editor.value?.isActive({ textAlign: lado }) ?? false;
const corAtual = (): string | undefined => (editor.value?.getAttributes('textStyle').color as string | undefined)?.toLowerCase();
const naTabela = computed<boolean>(() => ativo('table'));

const cadeia = () => editor.value!.chain().focus();

const estilos: { rotulo: string; dica: string; nivel: NivelTitulo | null }[] = [
    { rotulo: 'P', dica: 'Parágrafo', nivel: null },
    { rotulo: 'H2', dica: 'Título', nivel: 2 },
    { rotulo: 'H3', dica: 'Subtítulo', nivel: 3 },
    { rotulo: 'H4', dica: 'Tópico', nivel: 4 },
];
const estiloAtivo = (nivel: NivelTitulo | null): boolean => (nivel === null ? ativo('paragraph') : ativo('heading', { level: nivel }));
const aplicarEstilo = (nivel: NivelTitulo | null): void => {
    if (nivel === null) cadeia().setParagraph().run();
    else cadeia().setHeading({ level: nivel }).run();
};

// Ícones: traços 24×24 (estilo Lucide), desenhados com stroke="currentColor".
const marcas: { nome: string; dica: string; icone: string; alternar: () => void }[] = [
    { nome: 'bold', dica: 'Negrito', icone: 'M7 5h6a3.5 3.5 0 0 1 0 7H7zM7 12h7a3.5 3.5 0 0 1 0 7H7z', alternar: () => cadeia().toggleBold().run() },
    { nome: 'italic', dica: 'Itálico', icone: 'M19 4h-9M14 20H5M15 4 9 20', alternar: () => cadeia().toggleItalic().run() },
    { nome: 'underline', dica: 'Sublinhado', icone: 'M6 4v6a6 6 0 0 0 12 0V4M4 20h16', alternar: () => cadeia().toggleUnderline().run() },
    { nome: 'strike', dica: 'Tachado', icone: 'M16 5H10a3 3 0 0 0-2.8 4M14 12a3.5 3.5 0 0 1 0 7H7M4 12h16', alternar: () => cadeia().toggleStrike().run() },
    { nome: 'highlight', dica: 'Marca-texto', icone: 'm9 11-6 6v3h9l3-3M22 12l-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4', alternar: () => cadeia().toggleHighlight().run() },
];

const alinhamentos: { lado: Alinhamento; dica: string; icone: string }[] = [
    { lado: 'left', dica: 'Alinhar à esquerda', icone: 'M21 6H3M15 12H3M17 18H3' },
    { lado: 'center', dica: 'Centralizar', icone: 'M21 6H3M17 12H7M19 18H5' },
    { lado: 'right', dica: 'Alinhar à direita', icone: 'M21 6H3M21 12H9M21 18H7' },
    { lado: 'justify', dica: 'Justificar', icone: 'M21 6H3M21 12H3M21 18H3' },
];

const blocos: { nome: string; dica: string; icone: string; alternar: () => void }[] = [
    { nome: 'bulletList', dica: 'Lista com marcadores', icone: 'M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01', alternar: () => cadeia().toggleBulletList().run() },
    { nome: 'orderedList', dica: 'Lista numerada', icone: 'M10 6h11M10 12h11M10 18h11M4 4h1v5M4 9h2M6 20H4c0-1.5 2-2 2-3.5a1 1 0 0 0-2 0', alternar: () => cadeia().toggleOrderedList().run() },
    { nome: 'taskList', dica: 'Lista de tarefas', icone: 'M3 4h5v5H3zM3 16l2 2 3-4M12 7h9M12 16h9', alternar: () => cadeia().toggleTaskList().run() },
    { nome: 'blockquote', dica: 'Citação', icone: 'M6 7h4v5H5V9a2 2 0 0 1 1-2zM5 12c0 3 1 5 4 6M15 7h4v5h-5V9a2 2 0 0 1 1-2zM14 12c0 3 1 5 4 6', alternar: () => cadeia().toggleBlockquote().run() },
];

// Barra de ferramentas com tabindex itinerante: Tab entra e sai dela; as setas andam entre os botões.
const barra = ref<HTMLElement | null>(null);
const itensDaBarra = (): HTMLElement[] => Array.from(barra.value?.querySelectorAll<HTMLElement>('button') ?? []);
const tornarAtual = (item: HTMLElement): void => itensDaBarra().forEach((outro) => (outro.tabIndex = outro === item ? 0 : -1));
const garantirParadaDeTab = (): void => {
    const itens = itensDaBarra();
    if (itens.length && !itens.some((item) => item.tabIndex === 0)) tornarAtual(itens[0]);
};
const aoFocarNaBarra = (evento: FocusEvent): void => {
    if (evento.target instanceof HTMLButtonElement) tornarAtual(evento.target);
};
const aoTeclarNaBarra = (evento: KeyboardEvent): void => {
    const itens = itensDaBarra();
    const atual = itens.indexOf(evento.target as HTMLElement);
    if (atual < 0) return;
    const destino = { ArrowRight: atual + 1, ArrowLeft: atual - 1, Home: 0, End: itens.length - 1 }[evento.key];
    if (destino === undefined) return;
    evento.preventDefault();
    itens[(destino + itens.length) % itens.length].focus();
};
watch([editor, naTabela], () => nextTick(garantirParadaDeTab), { flush: 'post' });

// Diálogo único para link, imagem e vídeo (não é <form>: fica dentro do formulário do evento).
const dialogo = ref<HTMLDialogElement | null>(null);
const modo = ref<Modo | null>(null);
const endereco = ref<string>('');
const textoAlternativo = ref<string>('');
const arquivo = ref<File | null>(null);
const erro = ref<string>('');
// Campo a que o erro se refere (aria-invalid); falhas gerais, como sessão expirada, não apontam para nenhum.
const campoComErro = ref<CampoDoDialogo | null>(null);
const enviando = ref<boolean>(false);

const titulosDoDialogo: Record<Modo, string> = { link: 'Link', imagem: 'Imagem', video: 'Vídeo do YouTube' };

const limparErro = (): void => {
    erro.value = '';
    campoComErro.value = null;
};

const recusar = (campo: CampoDoDialogo | null, mensagem: string): void => {
    erro.value = mensagem;
    campoComErro.value = campo;
    focarPrimeiroErro(`#${props.id}-dialogo`);
};

const invalidoEm = (campo: CampoDoDialogo): true | undefined => (campoComErro.value === campo ? true : undefined);

/** aria-describedby do campo: a ajuda (se houver) e, quando o erro é dele, a mensagem de erro. */
const descricaoDoCampo = (campo: CampoDoDialogo, ajuda?: string): string | undefined =>
    [ajuda, invalidoEm(campo) && `${props.id}-dialogo-erro`].filter(Boolean).join(' ') || undefined;

// O conteúdo do diálogo depende do modo: abre depois de renderizado, para o autofocus valer.
const abrir = async (novoModo: Modo): Promise<void> => {
    modo.value = novoModo;
    limparErro();
    arquivo.value = null;
    textoAlternativo.value = '';
    endereco.value = novoModo === 'link' ? ((editor.value?.getAttributes('link').href as string | undefined) ?? '') : '';
    await nextTick();
    dialogo.value?.showModal();
};

const fechar = (): void => {
    dialogo.value?.close();
    modo.value = null;
    editor.value?.commands.focus();
};

const linkValido = (valor: string): string | null => {
    const comEsquema = /^www\./i.test(valor) ? `https://${valor}` : valor;
    if (!/^(https?:\/\/|mailto:)/i.test(comEsquema)) return null;
    try {
        new URL(comEsquema);
        return comEsquema;
    } catch {
        return null;
    }
};

const confirmarLink = (): void => {
    const valor = endereco.value.trim();
    if (valor === '') {
        cadeia().extendMarkRange('link').unsetLink().run();
        fechar();
        return;
    }
    const href = linkValido(valor);
    if (!href) {
        recusar('endereco', 'Use um endereço que comece com http://, https:// ou mailto:.');
        return;
    }
    if (editor.value?.state.selection.empty && !ativo('link')) {
        cadeia().insertContent({ type: 'text', text: valor, marks: [{ type: 'link', attrs: { href } }] }).run();
    } else {
        cadeia().extendMarkRange('link').setLink({ href }).run();
    }
    fechar();
};

const tokenXsrf = (): string => {
    const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
};

const mensagemDeFalha = async (resposta: Response): Promise<string> => {
    if (resposta.status === 422) {
        const corpo = (await resposta.json()) as { errors?: Record<string, string[]> };
        return corpo.errors?.imagem?.[0] ?? 'A imagem não foi aceita.';
    }
    if (resposta.status === 429) return 'Muitos envios seguidos. Aguarde um minuto e tente novamente.';
    if (resposta.status === 413) return 'A imagem passa do tamanho máximo permitido.';
    if (resposta.status === 401 || resposta.status === 419) return 'Sua sessão expirou. Recarregue a página e entre de novo.';
    return 'Não foi possível enviar a imagem. Tente novamente.';
};

const escolherArquivo = (evento: Event): void => {
    limparErro();
    arquivo.value = (evento.target as HTMLInputElement).files?.[0] ?? null;
};

const confirmarImagem = async (): Promise<void> => {
    const alt = textoAlternativo.value.trim();
    if (!arquivo.value) {
        recusar('arquivo', 'Escolha uma imagem.');
        return;
    }
    if (arquivo.value.size > LIMITE_IMAGEM) {
        recusar('arquivo', 'A imagem deve ter no máximo 5 MB.');
        return;
    }
    if (alt === '') {
        recusar('alt', 'Descreva a imagem no texto alternativo.');
        return;
    }

    const dados = new FormData();
    dados.append('imagem', arquivo.value);
    enviando.value = true;
    limparErro();
    try {
        const resposta = await fetch(props.urlImagem, {
            method: 'POST',
            body: dados,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': tokenXsrf() },
        });
        if (!resposta.ok) {
            // 422 e 413 são do arquivo; os demais (limite de envios, sessão) não são de campo nenhum.
            recusar(resposta.status === 422 || resposta.status === 413 ? 'arquivo' : null, await mensagemDeFalha(resposta));
            return;
        }
        const { url } = (await resposta.json()) as { url: string };
        cadeia().setImage({ src: url, alt }).run();
        fechar();
    } catch {
        recusar(null, 'Não foi possível enviar a imagem. Verifique a conexão e tente novamente.');
    } finally {
        enviando.value = false;
    }
};

const confirmarVideo = (): void => {
    const inseriu = editor.value?.chain().focus().setYoutubeVideo({ src: endereco.value.trim() }).run() ?? false;
    if (!inseriu) {
        recusar('endereco', 'Cole o endereço de um vídeo do YouTube (youtube.com ou youtu.be).');
        return;
    }
    fechar();
};

const confirmar = (): void => {
    if (modo.value === 'link') confirmarLink();
    else if (modo.value === 'imagem') void confirmarImagem();
    else if (modo.value === 'video') confirmarVideo();
};

const operacoesDeTabela: { rotulo: string; dica: string; executar: () => void }[] = [
    { rotulo: '+ Linha', dica: 'Adicionar linha abaixo', executar: () => cadeia().addRowAfter().run() },
    { rotulo: '+ Coluna', dica: 'Adicionar coluna à direita', executar: () => cadeia().addColumnAfter().run() },
    { rotulo: '− Linha', dica: 'Remover linha', executar: () => cadeia().deleteRow().run() },
    { rotulo: '− Coluna', dica: 'Remover coluna', executar: () => cadeia().deleteColumn().run() },
    { rotulo: 'Excluir tabela', dica: 'Excluir tabela', executar: () => cadeia().deleteTable().run() },
];

const classeBotao =
    'inline-flex min-h-10 min-w-10 items-center justify-center rounded-[8px] px-2 text-[0.8125rem] font-bold text-home-tinta hover:bg-nevoa aria-pressed:bg-acao aria-pressed:text-sobre-acao disabled:cursor-wait disabled:opacity-60';
const classeGrupo = 'flex flex-wrap items-center gap-0.5 border-hairline pr-1.5 not-last:border-r';
const classeCampo =
    'h-12 w-full rounded-[12px] border border-tinta-suave/70 bg-superficie px-4 text-base text-home-tinta placeholder:text-tinta-suave';
</script>

<template>
    <div
        class="editor-texto-rico overflow-hidden rounded-[12px] border bg-superficie focus-within:ring-2 focus-within:ring-acao"
        :class="invalido ? 'campo-invalido' : 'border-tinta-suave/70'"
    >
        <div
            v-if="editor"
            ref="barra"
            role="toolbar"
            :aria-label="`Formatação: ${rotulo}`"
            :aria-controls="id"
            class="flex flex-wrap items-center gap-x-1.5 gap-y-1 border-b border-hairline bg-parchment p-1.5"
            @focusin="aoFocarNaBarra"
            @keydown="aoTeclarNaBarra"
        >
            <div role="group" aria-label="Estilo do texto" :class="classeGrupo">
                <button
                    v-for="estilo in estilos"
                    :key="estilo.rotulo"
                    type="button"
                    tabindex="-1"
                    :class="classeBotao"
                    :title="estilo.dica"
                    :aria-label="estilo.dica"
                    :aria-pressed="estiloAtivo(estilo.nivel)"
                    @click="aplicarEstilo(estilo.nivel)"
                >
                    {{ estilo.rotulo }}
                </button>
            </div>

            <div role="group" aria-label="Destaques" :class="classeGrupo">
                <button
                    v-for="marca in marcas"
                    :key="marca.nome"
                    type="button"
                    tabindex="-1"
                    :class="classeBotao"
                    :title="marca.dica"
                    :aria-label="marca.dica"
                    :aria-pressed="ativo(marca.nome)"
                    @click="marca.alternar"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path :d="marca.icone" />
                    </svg>
                </button>
            </div>

            <div role="group" aria-label="Cor do texto" :class="classeGrupo">
                <button
                    type="button"
                    tabindex="-1"
                    :class="classeBotao"
                    title="Cor padrão"
                    aria-label="Cor padrão"
                    :aria-pressed="!corAtual()"
                    @click="cadeia().unsetColor().run()"
                >
                    <span aria-hidden="true" class="size-5 rounded-full border-2 border-current bg-[linear-gradient(135deg,transparent_45%,currentColor_45%,currentColor_55%,transparent_55%)]"></span>
                </button>
                <button
                    v-for="cor in CORES"
                    :key="cor.valor"
                    type="button"
                    tabindex="-1"
                    :class="classeBotao"
                    :title="`Cor: ${cor.nome}`"
                    :aria-label="`Cor: ${cor.nome}`"
                    :aria-pressed="corAtual() === cor.valor"
                    @click="cadeia().setColor(cor.valor).run()"
                >
                    <span aria-hidden="true" class="size-5 rounded-full border border-white/70 ring-1 ring-tinta-suave/50" :style="{ backgroundColor: cor.valor }"></span>
                </button>
            </div>

            <div role="group" aria-label="Alinhamento" :class="classeGrupo">
                <button
                    v-for="alinhamento in alinhamentos"
                    :key="alinhamento.lado"
                    type="button"
                    tabindex="-1"
                    :class="classeBotao"
                    :title="alinhamento.dica"
                    :aria-label="alinhamento.dica"
                    :aria-pressed="alinhado(alinhamento.lado)"
                    @click="cadeia().setTextAlign(alinhamento.lado).run()"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path :d="alinhamento.icone" />
                    </svg>
                </button>
            </div>

            <div role="group" aria-label="Listas e blocos" :class="classeGrupo">
                <button
                    v-for="bloco in blocos"
                    :key="bloco.nome"
                    type="button"
                    tabindex="-1"
                    :class="classeBotao"
                    :title="bloco.dica"
                    :aria-label="bloco.dica"
                    :aria-pressed="ativo(bloco.nome)"
                    @click="bloco.alternar"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path :d="bloco.icone" />
                    </svg>
                </button>
                <button type="button" tabindex="-1" :class="classeBotao" title="Linha divisória" aria-label="Linha divisória" @click="cadeia().setHorizontalRule().run()">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M3 12h18M8 6h8M8 18h8" />
                    </svg>
                </button>
            </div>

            <div role="group" aria-label="Inserir" :class="classeGrupo">
                <button type="button" tabindex="-1" :class="classeBotao" title="Link" aria-label="Link" :aria-pressed="ativo('link')" aria-haspopup="dialog" @click="abrir('link')">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
                    </svg>
                </button>
                <button type="button" tabindex="-1" :class="classeBotao" title="Imagem" aria-label="Imagem" aria-haspopup="dialog" @click="abrir('imagem')">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="16" rx="2" />
                        <circle cx="9" cy="10" r="1.5" />
                        <path d="m21 16-5-5-9 9" />
                    </svg>
                </button>
                <button type="button" tabindex="-1" :class="classeBotao" title="Vídeo do YouTube" aria-label="Vídeo do YouTube" aria-haspopup="dialog" @click="abrir('video')">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="5" width="20" height="14" rx="3" />
                        <path d="m10 9 5 3-5 3z" />
                    </svg>
                </button>
                <button
                    type="button"
                    tabindex="-1"
                    :class="classeBotao"
                    title="Tabela 3 × 3 com cabeçalho"
                    aria-label="Inserir tabela 3 por 3 com cabeçalho"
                    @click="cadeia().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="16" rx="2" />
                        <path d="M3 10h18M3 15h18M9 4v16M15 4v16" />
                    </svg>
                </button>
            </div>

            <div v-if="naTabela" role="group" aria-label="Tabela" :class="classeGrupo">
                <button
                    v-for="operacao in operacoesDeTabela"
                    :key="operacao.rotulo"
                    type="button"
                    tabindex="-1"
                    :class="classeBotao"
                    :title="operacao.dica"
                    :aria-label="operacao.dica"
                    @click="operacao.executar"
                >
                    {{ operacao.rotulo }}
                </button>
            </div>

            <div role="group" aria-label="Histórico" :class="classeGrupo">
                <button type="button" tabindex="-1" :class="classeBotao" title="Desfazer (Ctrl+Z)" aria-label="Desfazer" @click="cadeia().undo().run()">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 14 4 9l5-5M4 9h10.5a5.5 5.5 0 0 1 0 11H11" />
                    </svg>
                </button>
                <button type="button" tabindex="-1" :class="classeBotao" title="Refazer (Ctrl+Y)" aria-label="Refazer" @click="cadeia().redo().run()">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m15 14 5-5-5-5M20 9H9.5a5.5 5.5 0 0 0 0 11H13" />
                    </svg>
                </button>
                <button
                    type="button"
                    tabindex="-1"
                    :class="classeBotao"
                    title="Limpar formatação"
                    aria-label="Limpar formatação"
                    @click="cadeia().unsetAllMarks().clearNodes().unsetTextAlign().run()"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 7V4h16v3M9 20h6M12 4v16M4 19 20 5" />
                    </svg>
                </button>
            </div>
        </div>

        <EditorContent :editor="editor" />

        <dialog
            :id="`${id}-dialogo`"
            ref="dialogo"
            :aria-labelledby="`${id}-dialogo-titulo`"
            class="m-auto w-[min(30rem,calc(100vw-2rem))] rounded-card border border-hairline bg-superficie p-6 text-home-tinta backdrop:bg-noite/60"
            @close="modo = null"
        >
            <div v-if="modo" class="flex flex-col gap-4">
                <h2 :id="`${id}-dialogo-titulo`" class="text-[1.25rem] leading-tight font-bold">{{ titulosDoDialogo[modo] }}</h2>

                <!-- No link não há campo obrigatório (endereço vazio remove o link): o aviso é só da imagem e do vídeo. -->
                <AvisoCamposObrigatorios v-if="modo !== 'link'" />

                <label v-if="modo !== 'imagem'" class="flex flex-col gap-1.5 text-[0.9375rem] font-semibold">
                    <span>
                        {{ modo === 'link' ? 'Endereço (https:// ou mailto:)' : 'Endereço do vídeo' }}<span v-if="modo === 'video'" aria-hidden="true" class="ml-0.5 text-invalido-texto">*</span>
                    </span>
                    <input
                        v-model="endereco"
                        type="url"
                        autofocus
                        :required="modo === 'video'"
                        :placeholder="modo === 'link' ? 'https://' : 'https://www.youtube.com/watch?v=…'"
                        :class="classeCampo"
                        :aria-invalid="invalidoEm('endereco')"
                        :aria-describedby="descricaoDoCampo('endereco')"
                        @input="limparErro"
                        @keydown.enter.prevent="confirmar"
                    />
                </label>
                <p v-if="modo === 'link'" class="-mt-2 text-[0.8125rem] text-tinta-suave">Deixe vazio para remover o link.</p>

                <template v-if="modo === 'imagem'">
                    <label class="flex flex-col gap-1.5 text-[0.9375rem] font-semibold">
                        <span>Arquivo (JPG, PNG ou WEBP, até 5 MB)<span aria-hidden="true" class="ml-0.5 text-invalido-texto">*</span></span>
                        <!-- O destaque global não vale para campo de arquivo: com erro, a classe campo-invalido desenha a caixa. -->
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            autofocus
                            required
                            class="text-[0.9375rem] font-normal file:mr-3 file:min-h-11 file:rounded-pill file:border file:border-tinta-suave/70 file:bg-superficie file:px-4 file:font-semibold file:text-home-tinta"
                            :class="invalidoEm('arquivo') && 'campo-invalido rounded-[12px] p-1'"
                            :aria-invalid="invalidoEm('arquivo')"
                            :aria-describedby="descricaoDoCampo('arquivo')"
                            @change="escolherArquivo"
                        />
                    </label>
                    <label class="flex flex-col gap-1.5 text-[0.9375rem] font-semibold">
                        <span>Texto alternativo<span aria-hidden="true" class="ml-0.5 text-invalido-texto">*</span></span>
                        <input
                            v-model="textoAlternativo"
                            type="text"
                            required
                            maxlength="250"
                            placeholder="Ex.: Roda de capoeira no Terreiro de Jesus"
                            :class="classeCampo"
                            :aria-invalid="invalidoEm('alt')"
                            :aria-describedby="descricaoDoCampo('alt', `${id}-alt-ajuda`)"
                            @input="limparErro"
                            @keydown.enter.prevent="confirmar"
                        />
                        <span :id="`${id}-alt-ajuda`" class="text-[0.8125rem] font-normal text-tinta-suave">Descreva a imagem para quem usa leitor de tela.</span>
                    </label>
                </template>

                <p v-if="erro" :id="`${id}-dialogo-erro`" role="alert" class="text-[0.875rem] font-semibold text-invalido-texto">{{ erro }}</p>

                <div class="mt-2 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="pressionavel inline-flex min-h-11 items-center justify-center rounded-pill border border-tinta-suave/70 bg-superficie px-5 text-[0.8125rem] font-bold tracking-[0.12em] uppercase hover:border-acao hover:text-acao"
                        @click="fechar"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        :disabled="enviando"
                        class="pressionavel inline-flex min-h-11 items-center justify-center rounded-pill bg-acao px-5 text-[0.8125rem] font-bold tracking-[0.12em] text-sobre-acao uppercase hover:bg-acao-escuro disabled:cursor-wait disabled:opacity-70"
                        @click="confirmar"
                    >
                        {{ enviando ? 'Enviando…' : 'Inserir' }}
                    </button>
                </div>
            </div>
        </dialog>
    </div>
</template>
