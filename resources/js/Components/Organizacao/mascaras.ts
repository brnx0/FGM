// Máscaras e consultas do formulário da organização. As máscaras só ajudam a digitar: o servidor
// tira a pontuação e valida.

/** (71) 99999-9999 para celular e (71) 3333-3333 para fixo, como no cadastro de pessoa física. */
export const formatarTelefone = (valor: string): string => {
    const digitos = valor.replace(/\D/g, '').slice(0, 11);
    if (digitos.length <= 2) return digitos ? `(${digitos}` : '';
    if (digitos.length <= 6) return `(${digitos.slice(0, 2)}) ${digitos.slice(2)}`;
    const corte = digitos.length === 11 ? 7 : 6;
    return `(${digitos.slice(0, 2)}) ${digitos.slice(2, corte)}-${digitos.slice(corte)}`;
};

export const formatarCep = (valor: string): string => {
    const digitos = valor.replace(/\D/g, '').slice(0, 8);
    return digitos.length > 5 ? `${digitos.slice(0, 5)}-${digitos.slice(5)}` : digitos;
};

export const formatarCpf = (valor: string): string => {
    const d = valor.replace(/\D/g, '').slice(0, 11);
    if (d.length <= 3) return d;
    if (d.length <= 6) return `${d.slice(0, 3)}.${d.slice(3)}`;
    if (d.length <= 9) return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6)}`;
    return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`;
};

/** CNPJ sem pontuação, em maiúsculas: letras e números nas 12 primeiras posições, só números nas 2 últimas. */
export const normalizarCnpj = (valor: string): string => {
    let resultado = '';
    for (const caractere of valor.toUpperCase()) {
        if (resultado.length >= 14) break;
        const aceito = resultado.length < 12 ? /[0-9A-Z]/ : /[0-9]/;
        if (aceito.test(caractere)) resultado += caractere;
    }
    return resultado;
};

/** 12ABC34501DE35 → 12.ABC.345/01DE-35, enquanto a pessoa digita. */
export const formatarCnpj = (valor: string): string => {
    const c = normalizarCnpj(valor);
    const partes: [number, number, string][] = [
        [0, 2, ''],
        [2, 5, '.'],
        [5, 8, '.'],
        [8, 12, '/'],
        [12, 14, '-'],
    ];
    return partes.map(([inicio, fim, separador]) => (c.length > inicio ? separador + c.slice(inicio, fim) : '')).join('');
};

// Mesmo cálculo do App\Support\Cnpj: valor de cada caractere = código ASCII − 48, pesos 2 a 9, módulo 11.
const digitoVerificador = (base: string): number => {
    let soma = 0;
    let peso = 2;
    for (let i = base.length - 1; i >= 0; i--) {
        soma += (base.charCodeAt(i) - 48) * peso;
        peso = peso === 9 ? 2 : peso + 1;
    }
    const resto = soma % 11;
    return resto < 2 ? 0 : 11 - resto;
};

export const cnpjValido = (valor: string): boolean => {
    const c = normalizarCnpj(valor);
    if (!/^[0-9A-Z]{12}[0-9]{2}$/.test(c) || /^(.)\1{13}$/.test(c)) return false;
    const dv1 = digitoVerificador(c.slice(0, 12));
    const dv2 = digitoVerificador(c.slice(0, 12) + dv1);
    return c.slice(12) === `${dv1}${dv2}`;
};

/** 000000-0 */
export const formatarInscricaoMunicipal = (valor: string): string => {
    const d = valor.replace(/\D/g, '').slice(0, 7);
    return d.length > 6 ? `${d.slice(0, 6)}-${d.slice(6)}` : d;
};

/** 00.000.00-0 */
export const formatarInscricaoEstadual = (valor: string): string => {
    const d = valor.replace(/\D/g, '').slice(0, 8);
    if (d.length <= 2) return d;
    if (d.length <= 5) return `${d.slice(0, 2)}.${d.slice(2)}`;
    if (d.length <= 7) return `${d.slice(0, 2)}.${d.slice(2, 5)}.${d.slice(5)}`;
    return `${d.slice(0, 2)}.${d.slice(2, 5)}.${d.slice(5, 7)}-${d.slice(7)}`;
};

/**
 * GET nas consultas do cadastro (mesma origem, sessão). Qualquer falha — rede, 422, 429 do limitador
 * compartilhado, resposta inesperada — vira null, e o formulário segue à mão.
 */
export const consultar = async <T>(url: string, sinal?: AbortSignal): Promise<T | null> => {
    try {
        const resposta = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: sinal,
        });
        return resposta.ok ? ((await resposta.json()) as T) : null;
    } catch {
        return null;
    }
};

/** Comparação sem acento e sem diferença de caixa, para as listas com busca. */
export const semAcento = (texto: string): string =>
    texto
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
