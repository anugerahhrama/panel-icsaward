import { Link, usePage } from '@inertiajs/react';
import {
    ClipboardList,
    Gavel,
    LayoutGrid,
    Presentation,
    Settings,
    Trophy,
    Tags,
    UserCog,
    Users,
} from 'lucide-react';
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
import { dashboard as adminDashboard } from '@/routes/admin';
import adminAccounts from '@/routes/admin/accounts';
import assessmentTemplates from '@/routes/admin/assessment-templates';
import categories from '@/routes/admin/categories';
import judges from '@/routes/admin/judges';
import paperSubmissions from '@/routes/admin/participants/papers';
import pitching from '@/routes/admin/pitching';
import registrations from '@/routes/admin/participants/registrations';
import scoreRecap from '@/routes/admin/score-recap';
import emailSettings from '@/routes/admin/settings/email';
import fileSettings from '@/routes/admin/settings/files';
import judgingSettings from '@/routes/admin/settings/judging';
import landingApiSettings from '@/routes/admin/settings/landing-api';
import registrationSettings from '@/routes/admin/settings/registration';
import { dashboard as judgeDashboard } from '@/routes/judge';
import judgeSubmissions from '@/routes/judge/submissions';
import type { Auth, NavItem } from '@/types';

const participantNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const judgeNavItems: NavItem[] = [
    {
        title: 'Overview',
        href: judgeDashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'My Submissions',
        href: judgeSubmissions.index(),
        icon: ClipboardList,
    },
];

const adminNavItems = (isSuperadmin: boolean): NavItem[] => [
    {
        title: 'Overview',
        href: adminDashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Categories',
        href: categories.index(),
        icon: Tags,
        items: [
            {
                title: 'Categories',
                href: categories.index(),
            },
            {
                title: 'Assessment Templates',
                href: assessmentTemplates.index(),
            },
        ],
    },
    {
        title: 'Judges',
        href: judges.index(),
        icon: Gavel,
    },
    {
        title: 'Participants',
        href: registrations.index().url.replace(/\/[^/]+$/, ''),
        icon: Users,
        items: [
            {
                title: 'Registrations',
                href: registrations.index(),
            },
            {
                title: 'Paper Submissions',
                href: paperSubmissions.index(),
            },
        ],
    },
    {
        title: 'Score Recap',
        href: scoreRecap.index(),
        icon: Trophy,
    },
    {
        title: 'Pitching',
        href: pitching.index(),
        icon: Presentation,
    },
    ...(isSuperadmin
        ? [
              {
                  title: 'Admin Accounts',
                  href: adminAccounts.index(),
                  icon: UserCog,
              },
          ]
        : []),
    {
        title: 'Settings',
        href: registrationSettings.edit().url.replace(/\/[^/]+$/, ''),
        icon: Settings,
        items: [
            {
                title: 'Registration & Deadlines',
                href: registrationSettings.edit(),
            },
            {
                title: 'Judging',
                href: judgingSettings.edit(),
            },
            {
                title: 'Files & Terms',
                href: fileSettings.edit(),
            },
            {
                title: 'Email Templates',
                href: emailSettings.edit(),
            },
            ...(isSuperadmin
                ? [{ title: 'Landing API', href: landingApiSettings.edit() }]
                : []),
        ],
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isAdmin = ['superadmin', 'admin'].includes(auth.user.role);
    const isJudge = auth.user.role === 'judge';
    const homeHref = isAdmin
        ? adminDashboard()
        : isJudge
          ? judgeDashboard()
          : dashboard();
    const navItems = isAdmin
        ? adminNavItems(auth.user.role === 'superadmin')
        : isJudge
          ? judgeNavItems
          : participantNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={homeHref} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
