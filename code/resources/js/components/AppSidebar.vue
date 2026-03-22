<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChartCandlestick,
    LayoutGrid,
    RefreshCcw,
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
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
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
        title: 'Stocks',
        href: dashboardRoutes.stocks(),
        icon: TableProperties,
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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
