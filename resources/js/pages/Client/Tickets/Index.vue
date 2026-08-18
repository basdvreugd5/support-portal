<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create } from '@/routes/tickets';
import type { Ticket } from '@/types';

defineProps<{
    tickets: Ticket[];
}>();

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

function statusVariant(value: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        open: 'secondary',
        in_progress: 'default',
        resolved: 'outline',
        closed: 'outline',
    };

    return variants[value] ?? 'secondary';
}

function priorityVariant(value: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        high: 'destructive',
        normal: 'default',
        low: 'secondary',
    };

    return variants[value] ?? 'default';
}

function slaVariant(value: string | null): 'default' | 'secondary' | 'destructive' | 'outline' {
    const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        overdue: 'destructive',
        due_soon: 'default',
        on_track: 'secondary',
    };

    return variants[value ?? ''] ?? 'outline';
}
</script>

<template>
    <Head title="Mijn tickets" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Mijn tickets</h1>
                <p class="text-muted-foreground text-sm mt-1">
                    Alle tickets van uw organisatie.
                </p>
            </div>

            <Button as-child>
                <Link :href="create().url">Nieuw ticket</Link>
            </Button>
        </div>

        <div class="overflow-hidden rounded-xl border border-sidebar-border/70">
            <table class="w-full text-sm">
                <thead class="bg-secondary/40 text-muted-foreground">
                    <tr class="text-left">
                        <th class="px-4 py-3 font-medium">Ticket</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Prioriteit</th>
                        <th class="px-4 py-3 font-medium">SLA</th>
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
                                :href="`/tickets/${ticket.id}`"
                                class="font-medium underline-offset-4 hover:underline"
                            >
                                {{ ticket.title }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            <Badge :variant="statusVariant(ticket.status.value)">
                                {{ ticket.status.label }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <Badge :variant="priorityVariant(ticket.priority.value)">
                                {{ ticket.priority.label }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <Badge v-if="ticket.sla_status" :variant="slaVariant(ticket.sla_status.value)">
                                {{ ticket.sla_status.label }}
                            </Badge>
                            <span v-else class="text-muted-foreground">-</span>
                        </td>
                        <td class="text-muted-foreground px-4 py-3">
                            {{ formatDate(ticket.created_at) }}
                        </td>
                    </tr>
                    <tr v-if="tickets.length === 0">
                        <td class="text-muted-foreground px-4 py-8 text-center" colspan="5">
                            Nog geen tickets. Maak het eerste ticket aan.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>