import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Safety net: auto-refresh on 419 CSRF token mismatch for non-Inertia AJAX calls
let isReloadingOn419 = false;
window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error?.response?.status;
        const isInertiaRequest = Boolean(error?.config?.headers?.['X-Inertia']);
        if (status === 419 && !isInertiaRequest && !isReloadingOn419) {
            isReloadingOn419 = true;
            window.location.reload();
            return new Promise(() => {});
        }
        return Promise.reject(error);
    }
);
