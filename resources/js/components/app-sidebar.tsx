import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { SharedData, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, CreditCard, Folder, LayoutGrid, ReceiptText } from 'lucide-react';
import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        url: 'https://github.com/iamceeso/saasphp',
        icon: Folder,
    },
    {
        title: 'Documentation',
        url: 'https://saasphp.com',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { modules } = usePage<SharedData>().props;
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            url: '/dashboard',
            icon: LayoutGrid,
        },
        ...(modules.billing.enabled && modules.billing.navigation.enabled && modules.billing.navigation.show_pricing
            ? [{
                title: 'Pricing',
                url: '/billing/pricing',
                icon: CreditCard,
            }]
            : []),
        ...(modules.billing.enabled && modules.billing.navigation.enabled && modules.billing.navigation.show_subscriptions
            ? [{
                title: 'Subscriptions',
                url: '/subscriptions',
                icon: ReceiptText,
            }]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
