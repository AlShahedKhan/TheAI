import { Link, usePage } from '@inertiajs/react';
import {
    Clapperboard,
    Coins,
    CreditCard,
    FileText,
    LayoutGrid,
    MessageCircle,
    ShieldCheck,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
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
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const dashboardUrl = page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug)
        : '/';
    const isAdmin = Boolean(page.props.auth.user?.is_admin);
    const creditBalance = Number(page.props.creditBalance ?? 0);

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
        {
            title: 'Chat',
            href: '/chat',
            icon: MessageCircle,
        },
        {
            title: 'Video',
            href: '/videos',
            icon: Clapperboard,
        },
        {
            title: 'Usage',
            href: '/usage',
            icon: CreditCard,
        },
        {
            title: 'Credits',
            href: '/credits',
            icon: Coins,
        },
        ...(isAdmin
            ? [
                  {
                      title: 'Admin',
                      href: '/admin',
                      icon: ShieldCheck,
                  },
              ]
            : []),
    ];

    const footerNavItems: NavItem[] = [
        {
            title: 'Terms',
            href: '/terms',
            icon: FileText,
        },
        {
            title: 'Acceptable Use',
            href: '/acceptable-use',
            icon: FileText,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TeamSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <div className="mx-2 rounded-md border bg-card px-3 py-2 text-sm">
                    <div className="text-xs text-muted-foreground">
                        Credit balance
                    </div>
                    <div className="font-semibold">
                        {new Intl.NumberFormat().format(creditBalance)}
                    </div>
                </div>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
