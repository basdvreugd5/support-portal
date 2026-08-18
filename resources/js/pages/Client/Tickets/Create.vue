<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index, store } from '@/routes/tickets';
import type { Organization } from '@/types';

defineProps<{
    organizations?: Organization[];
}>();
</script>

<template>
    <Head title="Nieuw ticket" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-6">
        <Button as-child variant="ghost" class="w-fit px-2">
            <Link :href="index().url">Terug naar tickets</Link>
        </Button>

        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Nieuw ticket</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Beschrijf uw vraag of probleem zodat de supportdienst snel aan
                de slag kan.
            </p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Ticketgegevens</CardTitle>
                <CardDescription>
                    Vul minimaal een titel, omschrijving en prioriteit in.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="store.form()"
                    v-slot="{ errors, processing }"
                    class="grid gap-6"
                >
                    <div class="grid gap-2">
                        <Label for="title">Titel</Label>
                        <Input
                            id="title"
                            name="title"
                            type="text"
                            required
                            autofocus
                            placeholder="Korte samenvatting van het probleem"
                        />
                        <InputError :message="errors.title" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Omschrijving</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="6"
                            required
                            placeholder="Beschrijf het probleem, eventuele foutmeldingen en stappen om het te reproduceren..."
                            class="w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30"
                        />
                        <InputError :message="errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="priority">Prioriteit</Label>
                        <select
                            id="priority"
                            name="priority"
                            class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30"
                        >
                            <option value="low">Laag</option>
                            <option value="normal" selected>Normaal</option>
                            <option value="high">Hoog</option>
                        </select>
                        <InputError :message="errors.priority" />
                    </div>

                    <div
                        v-if="organizations && organizations.length > 0"
                        class="grid gap-2"
                    >
                        <Label for="organization_id">Organisatie</Label>
                        <select
                            id="organization_id"
                            name="organization_id"
                            required
                            class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30"
                        >
                            <option value="" disabled selected>
                                Kies een organisatie
                            </option>
                            <option
                                v-for="organization in organizations"
                                :key="organization.id"
                                :value="organization.id"
                            >
                                {{ organization.name }}
                            </option>
                        </select>
                        <InputError :message="errors.organization_id" />
                    </div>

                    <Button
                        type="submit"
                        class="w-fit"
                        data-test="create-ticket-button"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" />
                        Ticket aanmaken
                    </Button>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
