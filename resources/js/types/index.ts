import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    modules: {
        billing: {
            enabled: boolean;
            navigation: {
                enabled: boolean;
                show_pricing: boolean;
                show_subscriptions: boolean;
            };
        };
    };
    site: {
        name: string;
        url: string;
        description: string;
        logo: string;
        favicon: string;
        theme: string;
        timezone: string;
        locale: string;
        currency: string;
        language: string;
        date_format: string;
        time_format: string;
    };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
