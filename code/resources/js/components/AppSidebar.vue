<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    FilePenLine,
    Building2,
    ChartCandlestick,
    LayoutGrid,
    NotebookPen,
    RefreshCcw,
    Sigma,
    TableProperties,
    TrendingUp,
} from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
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
import dashboardRoutes from '@/routes/dashboard';
import type { NavGroup } from '@/types';

const navGroups: NavGroup[] = [
    {
        title: 'Platform',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
            {
                title: 'Recommendations',
                href: dashboardRoutes.recommendations(),
                icon: TrendingUp,
            },
            {
                title: 'Watch stock',
                href: dashboardRoutes.watchStock(),
                icon: Activity,
            },
            {
                title: 'Sectors',
                href: '/dashboard/sectors',
                icon: Building2,
            },
            {
                title: 'Strategies',
                href: dashboardRoutes.strategies(),
                icon: Sigma,
            },
            {
                title: 'Backtesting',
                href: '/dashboard/backtesting',
                icon: ChartCandlestick,
            },
            {
                title: 'Sync',
                href: dashboardRoutes.sync(),
                icon: RefreshCcw,
            },
            {
                title: 'Floorsheet',
                href: '/dashboard/floorsheet',
                icon: TableProperties,
            },
            {
                title: 'Stocks',
                href: dashboardRoutes.stocks(),
                icon: TableProperties,
            },
        ],
    },
    {
        title: 'Blog',
        items: [
            {
                title: 'Posts',
                href: '/dashboard/blog/posts',
                icon: NotebookPen,
            },
            {
                title: 'New post',
                href: '/dashboard/blog/posts/create',
                icon: FilePenLine,
            },
        ],
    },
];

</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
