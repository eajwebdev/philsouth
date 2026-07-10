export interface SiteRef {
    id: number;
    code: string;
    name: string;
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    roles: string[];
    permissions: string[];
    is_super: boolean;
    sites: SiteRef[];
}

export interface Flash {
    success?: string | null;
    error?: string | null;
}

export interface PageProps {
    name: string;
    auth: {
        user: AuthUser | null;
    };
    flash: Flash;
    ziggy: {
        location: string;
        [key: string]: unknown;
    };
    [key: string]: unknown;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}
