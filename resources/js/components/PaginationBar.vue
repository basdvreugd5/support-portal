<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import type { Pagination } from '@/types';

defineProps<{
    pagination: Pagination;
    pageUrl: (page: number) => string;
}>();

const linkClass =
    'inline-flex h-9 items-center gap-1 rounded-md border border-input bg-transparent px-3 text-sm text-muted-foreground shadow-xs transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50';
</script>

<template>
    <div
        v-if="pagination.total > pagination.per_page"
        class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
    >
        <span>
            Pagina {{ pagination.page }} van {{ pagination.last_page }} ({{
                pagination.total
            }}
            tickets)
        </span>
        <div class="flex gap-2">
            <Link
                v-if="pagination.page > 1"
                :href="pageUrl(pagination.page - 1)"
                preserve-scroll
                :class="linkClass"
            >
                <ChevronLeft class="size-4" />
                Vorige
            </Link>
            <Link
                v-if="pagination.page < pagination.last_page"
                :href="pageUrl(pagination.page + 1)"
                preserve-scroll
                :class="linkClass"
            >
                Volgende
                <ChevronRight class="size-4" />
            </Link>
        </div>
    </div>
</template>
