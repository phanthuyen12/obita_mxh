<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

import { Badge } from '@/components/ui/badge';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useActiveUrl } from '@/composables/useActiveUrl';
import { type NavItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
    label?: string;
}>();

const { urlIsActive } = useActiveUrl();
const page = usePage();
const expandedItems = ref(new Set<string>());

watch(
    () => page.url,
    () => {
        const next = new Set(expandedItems.value);

        props.items.forEach((item) => {
            if (item.children?.length && urlIsActive(item.href)) {
                next.add(item.title);
            }
        });

        expandedItems.value = next;
    },
    { immediate: true },
);

const toggleItem = (title: string) => {
    const next = new Set(expandedItems.value);

    if (next.has(title)) {
        next.delete(title);
    } else {
        next.add(title);
    }

    expandedItems.value = next;
};

const isChildActive = (item: NavItem) => {
    if (!item.activeQuery) return urlIsActive(item.href);

    const currentUrl = new URL(page.url, window.location.origin);
    const currentSection = currentUrl.searchParams.get('section');

    return currentSection === item.activeQuery;
};
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel v-if="label">
            {{ label }}
        </SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <div class="relative">
                    <SidebarMenuButton
                        as-child
                        :is-active="
                            urlIsActive(item.activePattern ?? item.href, {
                                exact: item.exact,
                                exclude: item.excludeActive,
                            })
                        "
                        :tooltip="item.title"
                    >
                        <button
                            v-if="item.children?.length"
                            type="button"
                            class="w-full text-left"
                            :class="item.badge ? 'pr-16' : ''"
                            @click="toggleItem(item.title)"
                        >
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </button>
                        <Link
                            v-else
                            :href="item.href"
                            :class="item.badge ? 'pr-16' : ''"
                        >
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                    <Badge
                        v-if="item.badge"
                        variant="warning"
                        class="pointer-events-none absolute end-2 top-1/2 -translate-y-1/2 px-1.5 group-data-[collapsible=icon]:hidden"
                    >
                        {{ item.badge }}
                    </Badge>
                </div>

                <SidebarMenuSub
                    v-if="
                        item.children?.length && expandedItems.has(item.title)
                    "
                    class="relative mx-4 mt-1 gap-0.5 border-l-0 px-2 pb-1 before:pointer-events-none before:absolute before:top-0 before:bottom-5 before:left-0 before:w-px before:bg-sidebar-border/70"
                >
                    <SidebarMenuSubItem
                        v-for="child in item.children"
                        :key="child.title"
                        class="before:pointer-events-none before:absolute before:top-0 before:-left-2 before:h-4 before:w-4 before:rounded-bl-2xl before:border-b before:border-l before:border-sidebar-border/70"
                    >
                        <SidebarMenuSubButton
                            as-child
                            :is-active="isChildActive(child)"
                            class="h-8 rounded-lg px-3 text-[13px] text-muted-foreground hover:bg-muted/50 hover:text-foreground data-[active=true]:bg-amber-500/10 data-[active=true]:font-semibold data-[active=true]:text-amber-950"
                        >
                            <Link :href="child.href">
                                <span>{{ child.title }}</span>
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
