import { nextTick } from 'vue';

/**
 * Depois que o servidor devolve os erros (onError do useForm), leva o foco ao primeiro campo destacado
 * dentro do formulário indicado (ex.: '#formulario-usuario').
 */
export const focarPrimeiroErro = (formulario: string): void => {
    nextTick(() => document.querySelector<HTMLElement>(`${formulario} [aria-invalid="true"]`)?.focus());
};
