import { Link } from '@inertiajs/react';
import { Briefcase, LayoutGrid, MessageSquare, Plug, Users, Webhook } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as clientsRoute } from '@/routes/clients';
import { index as integrationsRoute } from '@/routes/integrations';
import { index as projectsRoute } from '@/routes/projects';
import { index as smsHistoryRoute } from '@/routes/sms-history';
import { index as webhooksRoute } from '@/routes/webhooks';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Clients',
        href: clientsRoute(),
        icon: Users,
    },
    {
        title: 'Projects',
        href: projectsRoute(),
        icon: Briefcase,
    },
    {
        title: 'Webhooks',
        href: webhooksRoute(),
        icon: Webhook,
    },
    {
        title: 'SMS History',
        href: smsHistoryRoute(),
        icon: MessageSquare,
    },
    {
        title: 'Integrations',
        href: integrationsRoute(),
        icon: Plug,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
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
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
