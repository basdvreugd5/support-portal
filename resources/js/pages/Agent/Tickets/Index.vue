<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import PaginationBar from '@/components/PaginationBar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/tickets';
import type { Pagination, Ticket } from '@/types';

type Props = {
    tickets: Ticket[];
    pagination: Pagination;
    filters?: {
        status?: string;
        priority?: string;
        sla?: string;
    };
};

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({}),
});

const filters = reactive({
    status: props.filters.status ?? '',
    priority: props.filters.priority ?? '',
    sla: props.filters.sla ?? '',
});

function pageUrl(page: number): string {
    const query: Record<string, string> = { page: String(page) };

    if (filters.status) {
        query.status = filters.status;
    }

    if (filters.priority) {
        query.priority = filters.priority;
    }

    if (filters.sla) {
        query.sla = filters.sla;
    }

    return index({ query }).url;
}
function applyFilters(): void {
    router.get(index().url, filters, {
        preserveState: true,
        preserveScroll: true,
    });
}

function resetFilters(): void {
    filters.status = '';
    filters.priority = '';
    filters.sla = '';

    router.get(
        index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('nl-NL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatDeadline(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('nl-NL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function statusVariant(
    value: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    const variants: Record<
        string,
        'default' | 'secondary' | 'destructive' | 'outline'
    > = {
        open: 'secondary',
        in_progress: 'default',
        resolved: 'outline',
        closed: 'outline',
    };

    return variants[value] ?? 'secondary';
}

function priorityVariant(
    value: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    const variants: Record<
        string,
        'default' | 'secondary' | 'destructive' | 'outline'
    > = {
        high: 'destructive',
        normal: 'default',
        low: 'secondary',
    };

    return variants[value] ?? 'default';
}

function slaVariant(
    value: string | null,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    const variants: Record<
        string,
        'default' | 'secondary' | 'destructive' | 'outline'
    > = {
        overdue: 'destructive',
        due_soon: 'default',
        on_track: 'secondary',
    };

    return variants[value ?? ''] ?? 'outline';
}

const selectClass =
    'h-9 min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';
</script>

<template>
    <Head title="Alle tickets" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Alle tickets
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Ticketbeheer voor alle organisaties.
                </p>
            </div>

            <Button as-child>
                <Link :href="create().url">Nieuw ticket</Link>
            </Button>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1">
                <label class="text-sm font-medium" for="status">Status</label>
                <select
                    id="status"
                    v-model="filters.status"
                    class="w-40"
                    :class="selectClass"
                    @change="applyFilters"
                >
                    <option value="">Alle</option>
                    <option value="open">Open</option>
                    <option value="in_progress">In Behandeling</option>
                    <option value="resolved">Opgelost</option>
                    <option value="closed">Gesloten</option>
                </select>
            </div>

            <div class="grid gap-1">
                <label class="text-sm font-medium" for="priority"
                    >Prioriteit</label
                >
                <select
                    id="priority"
                    v-model="filters.priority"
                    class="w-40"
                    :class="selectClass"
                    @change="applyFilters"
                >
                    <option value="">Alle</option>
                    <option value="low">Laag</option>
                    <option value="normal">Normaal</option>
                    <option value="high">Hoog</option>
                </select>
            </div>

            <div class="grid gap-1">
                <label class="text-sm font-medium" for="sla">SLA</label>
                <select
                    id="sla"
                    v-model="filters.sla"
                    class="w-40"
                    :class="selectClass"
                    @change="applyFilters"
                >
                    <option value="">Alle</option>
                    <option value="on_track">Op Schema</option>
                    <option value="due_soon">Loopt Af</option>
                    <option value="overdue">Verlopen</option>
                </select>
            </div>

            <Button
                variant="ghost"
                class="h-9"
                :disabled="!filters.status && !filters.priority && !filters.sla"
                @click="resetFilters"
            >
                Filters wissen
            </Button>
        </div>

        <div class="overflow-hidden rounded-xl border border-sidebar-border/70">
            <table class="w-full text-sm">
                <thead class="bg-secondary/40 text-muted-foreground">
                    <tr class="text-left">
                        <th class="px-4 py-3 font-medium">Ticket</th>
                        <th class="px-4 py-3 font-medium">Organisatie</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Prioriteit</th>
                        <th class="px-4 py-3 font-medium">Toegewezen</th>
                        <th class="px-4 py-3 font-medium">SLA</th>
                        <th class="px-4 py-3 font-medium">Deadline</th>
                        <th class="px-4 py-3 font-medium">Aangemaakt</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="ticket in tickets"
                        :key="ticket.id"
                        class="border-t border-sidebar-border/70 transition-colors hover:bg-secondary/30"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="show(ticket.id).url"
                                class="font-medium underline-offset-4 hover:underline"
                            >
                                {{ ticket.title }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ ticket.organization?.name ?? '-' }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="statusVariant(ticket.status.value)"
                            >
                                {{ ticket.status.label }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    priorityVariant(ticket.priority.value)
                                "
                            >
                                {{ ticket.priority.label }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ ticket.assigned_to?.name ?? '-' }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                v-if="ticket.sla_status"
                                :variant="slaVariant(ticket.sla_status.value)"
                            >
                                {{ ticket.sla_status.label }}
                            </Badge>
                            <span v-else class="text-muted-foreground">-</span>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ formatDeadline(ticket.sla_due_at) }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ formatDate(ticket.created_at) }}
                        </td>
                    </tr>
                    <tr v-if="tickets.length === 0">
                        <td
                            class="px-4 py-8 text-center text-muted-foreground"
                            colspan="8"
                        >
                            Geen tickets gevonden voor de gekozen filters.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationBar :pagination="pagination" :page-url="pageUrl" />
    </div>
</template>
