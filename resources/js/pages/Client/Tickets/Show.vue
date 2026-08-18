<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { index, reply } from '@/routes/tickets';
import type { Ticket } from '@/types';

defineProps<{
    ticket: Ticket;
}>();

function formatDate(value: string | null): string {
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
</script>

<template>
    <Head :title="ticket.title" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
        <Button as-child variant="ghost" class="w-fit px-2">
            <Link :href="index().url">Terug naar tickets</Link>
        </Button>

        <div class="grid gap-3">
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ ticket.title }}
            </h1>
            <div class="flex flex-wrap items-center gap-2">
                <Badge :variant="statusVariant(ticket.status.value)">
                    {{ ticket.status.label }}
                </Badge>
                <Badge :variant="priorityVariant(ticket.priority.value)">
                    {{ ticket.priority.label }}
                </Badge>
                <Badge
                    v-if="ticket.sla_status"
                    :variant="slaVariant(ticket.sla_status.value)"
                >
                    SLA: {{ ticket.sla_status.label }}
                </Badge>
            </div>
            <p v-if="ticket.sla_due_at" class="text-sm text-muted-foreground">
                Deadline: {{ formatDate(ticket.sla_due_at) }}
            </p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Omschrijving</CardTitle>
            </CardHeader>
            <CardContent
                class="text-sm whitespace-pre-wrap text-muted-foreground"
            >
                {{ ticket.description }}
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Conversatie</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4">
                <div
                    v-if="ticket.messages && ticket.messages.length > 0"
                    class="grid gap-3"
                >
                    <div
                        v-for="message in ticket.messages"
                        :key="message.id"
                        class="grid gap-1 rounded-lg border border-sidebar-border/70 px-4 py-3"
                    >
                        <div
                            class="flex items-center justify-between gap-2 text-sm"
                        >
                            <span class="font-medium">{{
                                message.user?.name ?? 'Onbekende gebruiker'
                            }}</span>
                            <span class="text-xs text-muted-foreground">
                                {{ formatDate(message.created_at) }}
                            </span>
                        </div>
                        <p
                            class="text-sm whitespace-pre-wrap text-muted-foreground"
                        >
                            {{ message.body }}
                        </p>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Nog geen berichten.
                </p>

                <Form
                    v-bind="reply.form(ticket.id)"
                    :reset-on-success="['body']"
                    :preserve-scroll="true"
                    v-slot="{ errors, processing }"
                    class="grid gap-2 border-t border-sidebar-border/70 pt-4"
                >
                    <label class="text-sm font-medium" for="body"
                        >Reactie</label
                    >
                    <textarea
                        id="body"
                        name="body"
                        rows="4"
                        required
                        placeholder="Typ uw reactie..."
                        class="w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30"
                    />
                    <InputError :message="errors.body" />
                    <Button
                        type="submit"
                        class="w-fit"
                        :disabled="processing"
                        data-test="reply-button"
                    >
                        <Spinner v-if="processing" />
                        Verstuur reactie
                    </Button>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
