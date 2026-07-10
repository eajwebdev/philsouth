import { usePage } from '@inertiajs/react';
import type { PageProps, AuthUser } from '@/types';

export function useAuth() {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;

    const can = (permission: string): boolean => {
        if (!user) return false;
        if (user.is_super) return true;
        return user.permissions.includes(permission);
    };

    const hasRole = (...roles: string[]): boolean => {
        if (!user) return false;
        return roles.some((r) => user.roles.includes(r));
    };

    return {
        user: user as AuthUser | null,
        can,
        hasRole,
        isSuper: user?.is_super ?? false,
    };
}
