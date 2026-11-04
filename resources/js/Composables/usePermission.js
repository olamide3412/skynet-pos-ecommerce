import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePermission() {
    const page = usePage();

    const isSuperAdmin = computed(() => {
        return !!page.props.auth?.isSuperAdmin;
    });

    const userRoles = computed(() => {
        return page.props.auth?.roles || [];
    });

    const userPermissions = computed(() => {
        return page.props.auth?.permissions || {};
    });

    const can = (permission) => {
        if (isSuperAdmin.value) return true;
        return !!userPermissions.value[permission];
    };

    const canAny = (permissions = []) => {
        if (isSuperAdmin.value) return true;
        return permissions.some(p => !!userPermissions.value[p]);
    };

    const canAll = (permissions = []) => {
        if (isSuperAdmin.value) return true;
        return permissions.every(p => !!userPermissions.value[p]);
    };

    const hasRole = (role) => {
        if (isSuperAdmin.value) return true;
        return userRoles.value.includes(role);
    };

    return {
        isSuperAdmin,
        userRoles,
        userPermissions,
        can,
        canAny,
        canAll,
        hasRole,
    };
}

