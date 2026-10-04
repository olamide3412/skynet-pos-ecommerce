<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import Logo from '../../images/logo.png';

// Mobile overlay toggle
const isMobileOpen = ref(false);
// Desktop collapsed state — persist in localStorage
const isCollapsed = ref(false);

const navLinks = [
    {
        label: 'Dashboard',
        route: 'admin.dashboard',
        match: 'Admin/Dashboard',
        icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    },
    {
        label: 'Catalog',
        icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
        children: [
            { label: 'Products', route: 'admin.products.index', match: 'Admin/Products' },
            { label: 'Categories', route: 'admin.categories.index', match: 'Admin/Categories' },
            { label: 'Attributes', route: 'admin.attributes.index', match: 'Admin/Attributes' },
        ]
    },
    {
        label: 'Sales',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        children: [
            { label: 'Orders', route: 'admin.orders.index', match: 'Admin/Orders' },
            { label: 'Revenue', route: 'admin.revenue.index', match: 'Admin/Revenue' },
        ]
    },
    {
        label: 'Users',
        route: 'admin.users.index',
        match: 'Admin/Users',
        icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    },
    {
        label: 'POS Integration',
        route: 'admin.pos-integration.index',
        match: 'Admin/PosIntegration',
        icon: 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2',
    },
    {
        label: 'Settings',
        icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
        children: [
            { label: 'Store Config', route: 'admin.settings.index', match: 'Admin/Settings' },
            { label: 'Sliders', route: 'admin.sliders.index', match: 'Admin/Sliders' },
        ]
    },
];

const expandedMenus = ref([]);

const isActive = (link) => {
    const comp = usePage().component;
    if (link.match && (comp === link.match || comp.startsWith(link.match))) return true;
    if (link.children) {
        return link.children.some(child => comp === child.match || comp.startsWith(child.match));
    }
    return false;
};

const isChildActive = (child) => {
    const comp = usePage().component;
    return comp === child.match || comp.startsWith(child.match);
};

const toggleMenu = (label) => {
    if (expandedMenus.value.includes(label)) {
        expandedMenus.value = expandedMenus.value.filter(l => l !== label);
    } else {
        expandedMenus.value.push(label);
    }
};

const toggleMobile = () => {
    isMobileOpen.value = !isMobileOpen.value;
};

const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value;
    localStorage.setItem('admin_sidebar_collapsed', isCollapsed.value);
};

onMounted(() => {
    const saved = localStorage.getItem('admin_sidebar_collapsed');
    if (saved !== null) isCollapsed.value = saved === 'true';

    // Auto-expand menu that contains the active route
    navLinks.forEach(link => {
        if (link.children && isActive(link)) {
            expandedMenus.value.push(link.label);
        }
    });
});
</script>

<template>
    <div class="flex h-screen bg-gray-50 dark:bg-gray-900 overflow-hidden font-sans text-gray-900 dark:text-gray-100">

        <!-- Mobile Overlay -->
        <div v-show="isMobileOpen" class="fixed inset-0 z-20 bg-black/50 lg:hidden" @click="toggleMobile"></div>

        <!-- ── Sidebar ──────────────────────────────────────────────── -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-30 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 flex flex-col transition-all duration-300',
                'lg:relative lg:translate-x-0',
                isMobileOpen ? 'translate-x-0 w-64' : '-translate-x-full lg:translate-x-0',
                isCollapsed ? 'lg:w-16' : 'lg:w-64'
            ]"
        >
            <!-- Logo / Brand -->
            <div class="flex items-center h-16 border-b border-gray-200 dark:border-gray-700 px-3 overflow-hidden">
                <Link href="/" class="flex items-center gap-2 min-w-0">
                    <img :src="$page.props.store_settings.company_logo ? '/storage/' + $page.props.store_settings.company_logo : Logo" class="w-8 h-8 object-contain flex-shrink-0" alt="Logo"/>
                    <span v-show="!isCollapsed" class="text-base font-bold text-blue-600 dark:text-blue-400 whitespace-nowrap overflow-hidden transition-all duration-300">
                        {{ $page.props.store_settings.company_name || 'Skynet' }} Admin
                    </span>
                </Link>
                <!-- Desktop collapse toggle -->
                <button
                    @click="toggleCollapse"
                    class="hidden lg:flex ml-auto items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition flex-shrink-0"
                    :title="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                >
                    <svg class="w-4 h-4 transition-transform duration-300" :class="isCollapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                    </svg>
                </button>
            </div>

            <!-- Nav Links -->
            <nav class="flex-1 overflow-y-auto py-4 px-2 space-y-1">
                <template v-for="link in navLinks" :key="link.label">
                    <div class="relative group">
                        
                        <!-- Standalone Link -->
                        <Link v-if="link.route"
                            :href="route(link.route)"
                            :title="isCollapsed ? link.label : ''"
                            :class="[
                                'flex w-full items-center rounded-lg font-medium transition-colors cursor-pointer',
                                isCollapsed ? 'justify-center px-0 py-3' : 'px-4 py-3 gap-0',
                                isActive(link)
                                    ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'
                            ]"
                        >
                            <svg class="w-5 h-5 flex-shrink-0" :class="isCollapsed ? '' : 'mr-3'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="link.icon"/>
                            </svg>
                            <span v-show="!isCollapsed" class="whitespace-nowrap overflow-hidden transition-all duration-200 flex-1 text-left">
                                {{ link.label }}
                            </span>
                            <!-- Tooltip when collapsed -->
                            <span v-show="isCollapsed" class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded shadow-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity duration-150 z-50">
                                {{ link.label }}
                            </span>
                        </Link>
                        
                        <!-- Parent Group Button -->
                        <button v-else
                            @click="toggleMenu(link.label)"
                            :title="isCollapsed ? link.label : ''"
                            :class="[
                                'flex w-full items-center rounded-lg font-medium transition-colors cursor-pointer',
                                isCollapsed ? 'justify-center px-0 py-3' : 'px-4 py-3 gap-0',
                                isActive(link)
                                    ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'
                            ]"
                        >
                            <svg class="w-5 h-5 flex-shrink-0" :class="isCollapsed ? '' : 'mr-3'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="link.icon"/>
                            </svg>
                            <span v-show="!isCollapsed" class="whitespace-nowrap overflow-hidden transition-all duration-200 flex-1 text-left">
                                {{ link.label }}
                            </span>
                            <svg v-show="!isCollapsed" class="w-4 h-4 transition-transform duration-200 flex-shrink-0" :class="expandedMenus.includes(link.label) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <!-- Children (Accordion - when expanded config) -->
                        <div v-show="link.children && !isCollapsed && expandedMenus.includes(link.label)" class="pl-10 pr-2 mt-1 space-y-1 overflow-hidden transition-all duration-300">
                            <Link v-for="child in link.children" :key="child.label"
                                :href="route(child.route)"
                                :class="[
                                    'flex items-center px-3 py-2 text-sm rounded-lg transition-colors',
                                    isChildActive(child)
                                        ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400 font-bold'
                                        : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'
                                ]"
                            >
                                {{ child.label }}
                            </Link>
                        </div>

                        <!-- Children (Popover - when collapsed) -->
                        <div v-if="link.children && isCollapsed" 
                            class="absolute left-full top-0 ml-2 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-[60] py-2 pointer-events-none group-hover:pointer-events-auto">
                            <div class="px-4 py-2 font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-700 mb-1 tracking-wide">{{ link.label }}</div>
                            <Link v-for="child in link.children" :key="child.label"
                                :href="route(child.route)"
                                :class="[
                                    'block px-4 py-2 text-sm transition-colors',
                                    isChildActive(child)
                                        ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 font-bold'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'
                                ]"
                            >
                                {{ child.label }}
                            </Link>
                        </div>

                    </div>
                </template>
            </nav>

            <!-- Logout -->
            <div class="p-2 border-t border-gray-200 dark:border-gray-700">
                <Link
                    :href="route('admin.logout')"
                    method="post"
                    as="button"
                    :title="isCollapsed ? 'Logout' : ''"
                    :class="[
                        'flex w-full items-center rounded-lg text-sm text-red-600 dark:text-red-400 font-medium hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors group relative',
                        isCollapsed ? 'justify-center px-0 py-3' : 'px-4 py-2 gap-0'
                    ]"
                >
                    <svg class="w-5 h-5 flex-shrink-0" :class="isCollapsed ? '' : 'mr-3'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span v-show="!isCollapsed" class="whitespace-nowrap">Logout</span>
                    <span v-show="isCollapsed"
                        class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded shadow-lg whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity duration-150 z-50">
                        Logout
                    </span>
                </Link>
            </div>
        </aside>

        <!-- ── Main Content ─────────────────────────────────────────── -->
        <main class="flex-1 flex flex-col overflow-hidden min-w-0 min-h-0">
            <!-- Top Header -->
            <header class="h-16 flex-shrink-0 flex items-center justify-between border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <!-- Mobile hamburger -->
                    <button @click="toggleMobile" class="lg:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 hidden sm:block">Control Panel</h2>
                </div>
                <div class="flex items-center gap-4">
                    <ThemeToggle/>
                    <div class="hidden sm:flex items-center gap-2">
                        <div class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-sm">
                            {{ $page.props.auth.user?.name?.charAt(0)?.toUpperCase() || 'A' }}
                        </div>
                        <span class="text-sm font-medium">{{ $page.props.auth.user?.name }}</span>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="flex-1 min-h-0 overflow-x-hidden overflow-y-auto bg-gray-50 dark:bg-gray-900 p-4 sm:p-6 lg:p-8">
                <slot/>
            </div>
        </main>

        <FlashMessages/>
    </div>
</template>
