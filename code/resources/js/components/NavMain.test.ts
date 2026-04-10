import { mount } from '@vue/test-utils';
import { LayoutGrid } from 'lucide-vue-next';
import { describe, expect, it, vi } from 'vitest';
import NavMain from '@/components/NavMain.vue';

vi.mock('@/composables/useCurrentUrl', () => ({
    useCurrentUrl: () => ({
        isCurrentUrl: () => false,
    }),
}));

describe('NavMain', () => {
    it('renders grouped sidebar navigation', () => {
        const wrapper = mount(NavMain, {
            props: {
                groups: [
                    {
                        title: 'Platform',
                        items: [
                            { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
                        ],
                    },
                    {
                        title: 'Blog',
                        items: [
                            { title: 'Posts', href: '/dashboard/blog/posts', icon: LayoutGrid },
                            { title: 'New post', href: '/dashboard/blog/posts/create', icon: LayoutGrid },
                        ],
                    },
                ],
            },
            global: {
                stubs: {
                    Link: {
                        template: '<a><slot /></a>',
                    },
                    SidebarGroup: {
                        template: '<div><slot /></div>',
                    },
                    SidebarGroupLabel: {
                        template: '<div><slot /></div>',
                    },
                    SidebarMenu: {
                        template: '<div><slot /></div>',
                    },
                    SidebarMenuItem: {
                        template: '<div><slot /></div>',
                    },
                    SidebarMenuButton: {
                        template: '<div><slot /></div>',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Platform');
        expect(wrapper.text()).toContain('Dashboard');
        expect(wrapper.text()).toContain('Blog');
        expect(wrapper.text()).toContain('Posts');
        expect(wrapper.text()).toContain('New post');
    });
});
