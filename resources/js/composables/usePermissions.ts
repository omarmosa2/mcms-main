import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

function permissionMatches(grantedPermission: string, requestedPermission: string): boolean {
    if (grantedPermission === requestedPermission || grantedPermission === '*') {
        return true;
    }

    if (grantedPermission.endsWith('.*')) {
        return requestedPermission.startsWith(grantedPermission.slice(0, -1));
    }

    return false;
}

export function usePermissions() {
    const page = usePage();

    const permissions = computed<string[]>(() => {
        return ((page.props.auth as { permissions?: string[] } | undefined)?.permissions ?? []).filter(
            (value): value is string => typeof value === 'string',
        );
    });

    const roles = computed<string[]>(() => {
        return ((page.props.auth as { roles?: string[] } | undefined)?.roles ?? []).filter(
            (value): value is string => typeof value === 'string',
        );
    });

    const noClinicContext = computed(() => roles.value.length === 0);

    const can = (permission: string): boolean => {
        if (noClinicContext.value) {
            return true;
        }

        return permissions.value.some((grantedPermission) =>
            permissionMatches(grantedPermission, permission),
        );
    };

    return {
        permissions,
        roles,
        can,
    };
}
