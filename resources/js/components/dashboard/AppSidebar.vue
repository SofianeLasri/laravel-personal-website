<script setup lang="ts">
import NavFooter from '@/components/dashboard/NavFooter.vue';
import NavMain from '@/components/dashboard/NavMain.vue';
import NavUser from '@/components/dashboard/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useRoute } from '@/composables/useRoute';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    Award,
    BookOpen,
    Briefcase,
    Brush,
    Code,
    Folder,
    FolderOutput,
    Image as ImageIcon,
    Languages,
    LayoutGrid,
    LinkIcon,
    Logs,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const route = useRoute();

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: route('dashboard.index', undefined, false),
        icon: LayoutGrid,
    },
    {
        title: 'Créations',
        href: '#',
        icon: Brush,
        items: [
            {
                title: 'Liste',
                href: route('dashboard.creations.index', undefined, false),
            },
            {
                title: 'Brouillons',
                href: route('dashboard.creations.drafts.index', undefined, false),
            },
            {
                title: 'Éditeur',
                href: route('dashboard.creations.edit', undefined, false),
            },
        ],
    },
    {
        title: 'Technologies',
        href: route('dashboard.technology-experiences.index', undefined, false),
        icon: Code,
    },
    {
        title: 'Expériences',
        href: route('dashboard.experiences.index', undefined, false),
        icon: Briefcase,
    },
    {
        title: 'Certifications',
        href: route('dashboard.certifications.index', undefined, false),
        icon: Award,
    },
    {
        title: 'Réseaux sociaux',
        href: route('dashboard.social-media-links.index', undefined, false),
        icon: LinkIcon,
    },
    {
        title: 'Images',
        href: route('dashboard.pictures.index', undefined, false),
        icon: ImageIcon,
    },
    {
        title: 'Traductions',
        href: route('dashboard.translations.index', undefined, false),
        icon: Languages,
    },
    {
        title: 'Requêtes',
        href: route('dashboard.request-logs.index', undefined, false),
        icon: Logs,
    },
    {
        title: 'Export/Import',
        href: route('dashboard.data-management.index', undefined, false),
        icon: FolderOutput,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard.index')">
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
