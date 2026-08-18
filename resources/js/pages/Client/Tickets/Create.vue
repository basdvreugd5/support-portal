<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index, store } from '@/routes/tickets';
</script>

<template>
    <Head title="Nieuw ticket" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-6">
        <Button as-child variant="ghost" class="w-fit px-2">
            <Link :href="index().url">Terug naar tickets</Link>
        </Button>

        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Nieuw ticket</h1>
            <p class="text-muted-foreground text-sm mt-1">
                Beschrijf uw vraag of probleem zodat de supportdienst snel aan de slag kan.
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
                <Form v-bind="store.form()" v-slot="{ errors, processing }" class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="title">Titel</Label>
                        <Input id="title" name="title" type="text" required autofocus placeholder="Korte samenvatting van het probleem" />
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
                            class="dark:bg-input/30 placeholder:text-muted-foreground border-input min-w-0 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                        />
                        <InputError :message="errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="priority">Prioriteit</Label>
                        <select
                            id="priority"
                            name="priority"
                            class="dark:bg-input/30 border-input h-9 min-w-0 w-full rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                        >
                            <option value="low">Laag</option>
                            <option value="normal" selected>Normaal</option>
                            <option value="high">Hoog</option>
                        </select>
                    </div>

                    <Button type="submit" class="w-fit" data-test="create-ticket-button" :disabled="processing">
                        <Spinner v-if="processing" />
                        Ticket aanmaken
                    </Button>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>