import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import PortalLayout from './Layouts/PortalLayout.vue';

createInertiaApp({
    title: (title) => (title ? `${title} · Mapa Cultura` : 'Mapa Cultura'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];
        page.default.layout ??= PortalLayout;
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
