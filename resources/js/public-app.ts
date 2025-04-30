import '../css/public.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createSSRApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { createI18n } from 'vue-i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/public/*.vue')),
    setup({ el, App, props, plugin }) {
        const locale: string = (props.initialPage.props.locale as string) || 'fr';
        const messages: Record<string, string> = (props.initialPage.props.translations as Record<string, string>) || {};

        const i18n = createI18n({
            legacy: false,
            globalInjection: true,
            locale: locale,
            messages: {
                [locale]: messages,
            },
        });

        createSSRApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
}).then();
