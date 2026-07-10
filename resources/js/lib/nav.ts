import {
    LayoutDashboard,
    Building2,
    Users,
    Package,
    Boxes,
    Truck,
    ClipboardList,
    ArrowLeftRight,
    type LucideIcon,
} from 'lucide-react';

export interface NavItem {
    label: string;
    routeName: string;
    icon: LucideIcon;
    /** Show when the user has ANY of these permissions (empty = always). */
    permissions?: string[];
    /** Match these route-name prefixes for active state. */
    activeMatch?: string[];
}

export interface NavSection {
    heading?: string;
    items: NavItem[];
}

/**
 * Full app navigation. Items are filtered by permission at render time.
 * New sections/items are switched on as each build phase lands.
 */
export const NAV_SECTIONS: NavSection[] = [
    {
        items: [
            { label: 'Dashboard', routeName: 'dashboard', icon: LayoutDashboard },
        ],
    },
    {
        heading: 'Inventory',
        items: [
            {
                label: 'Stock',
                routeName: 'inventory.index',
                icon: Boxes,
                permissions: ['inventory.view'],
                activeMatch: ['inventory.'],
            },
            {
                label: 'Items',
                routeName: 'items.index',
                icon: Package,
                permissions: ['items.manage', 'inventory.view'],
                activeMatch: ['items.'],
            },
        ],
    },
    {
        heading: 'Operations',
        items: [
            {
                label: 'Receiving',
                routeName: 'receiving.index',
                icon: Truck,
                permissions: ['receiving.manage', 'inventory.view'],
                activeMatch: ['receiving.'],
            },
            {
                label: 'Withdrawals',
                routeName: 'withdrawals.index',
                icon: ClipboardList,
                permissions: ['withdrawal.create', 'withdrawal.approve', 'withdrawal.release', 'withdrawal.receive', 'inventory.view'],
                activeMatch: ['withdrawals.'],
            },
            {
                label: 'Transfers',
                routeName: 'transfers.index',
                icon: ArrowLeftRight,
                permissions: ['transfer.create', 'transfer.receive', 'inventory.view'],
                activeMatch: ['transfers.'],
            },
        ],
    },
    {
        heading: 'Administration',
        items: [
            {
                label: 'Sites',
                routeName: 'sites.index',
                icon: Building2,
                permissions: ['sites.manage', 'inventory.view'],
                activeMatch: ['sites.'],
            },
            {
                label: 'Users',
                routeName: 'users.index',
                icon: Users,
                permissions: ['users.manage'],
                activeMatch: ['users.'],
            },
        ],
    },
];
