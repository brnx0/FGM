import type { Component, DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import PortalLayout from './Layouts/PortalLayout.vue';

type PaginaVue = { default: DefineComponent & { layout?: Component } };

createInertiaApp({
    title: (title) => (title ? `${title} · Mapa Cultura` : 'Mapa Cultura'),
    resolve: (name) => {
        const pages = import.meta.glob<PaginaVue>('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];
        page.default.layout ??= PortalLayout;
        // Props compartilhadas do Inertia não devem cair como atributos em páginas de raiz fragmentada.
        page.default.inheritAttrs = false;
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#004D9F',
    },
});
