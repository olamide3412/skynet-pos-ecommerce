import './bootstrap';
import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp, Head, Link } from '@inertiajs/vue3'
import Layout from './Layouts/Layout.vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';
import { formatDate } from './Utils/dateFormat';
import 'aos/dist/aos.css';
import { createPinia } from 'pinia';
import { useThemeStore } from './Stores/themeStore';
import FlashMessages from './Components/FlashMessages.vue';


// AOS is now dynamically imported in setup
// AOS.init moved to setup



const pinia = createPinia();

import { FontAwesomeIcon } from './fontawesome';

createInertiaApp({
    title: (title) => `${title}`,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.use(plugin)
            .use(ZiggyVue)
            .use(Toast)
            .use(pinia);

        const themeStore = useThemeStore();
        themeStore.loadTheme();
        if (props.initialPage?.props?.store_settings) {
            const s = props.initialPage.props.store_settings;
            themeStore.applyBrandColors(s.theme_primary_color, s.theme_secondary_color, s.theme_dark_mode_text_color);
            if (s.company_logo) {
                const logoPath = '/storage/' + s.company_logo;
                const iconLink = document.querySelector("link[rel*='icon']");
                if (iconLink) {
                    iconLink.href = logoPath;
                }
            }
        }

        app.config.globalProperties.$formatDate = formatDate;

        app.component('Head', Head)
            .component('Link', Link)
            .component('FlashMessages', FlashMessages)
            .component('font-awesome-icon', FontAwesomeIcon);

        app.mount(el);

        // Smoothly dismiss initial splash screen once Vue app is mounted
        const splash = document.getElementById('app-splash-screen');
        if (splash) {
            splash.classList.add('splash-fade-out');
            setTimeout(() => {
                if (splash && splash.parentNode) {
                    splash.parentNode.removeChild(splash);
                }
            }, 380);
        }

        // ✅ Dynamically import AOS to split the chunk (Non-critical)
        import('aos').then((AOS) => {
            const aos = AOS.default || AOS;
            if (aos && aos.init) {
                aos.init({
                    duration: 1000,
                    once: true,
                });
            }
        }).catch(err => console.warn('AOS failed to load:', err));
    },
    progress: {
        delay: 250,
        color: '#FBA43D',
        includeCSS: true,
        showSpinner: false,
    },
})
