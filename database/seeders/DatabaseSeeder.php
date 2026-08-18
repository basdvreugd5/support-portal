<?php

namespace Database\Seeders;

use App\Enums\TicketMessageType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Organizations ------------------------------------------------------------------
        $acme = Organization::create(['name' => 'Acme BV']);
        $globex = Organization::create(['name' => 'Globex Corp']);

        // Users --------------------------------------------------------------------------
        $alice = $this->clientUser($acme, 'Alice A', 'client1@acme.test');
        $bob = $this->clientUser($acme, 'Bob B', 'client2@acme.test');
        $carl = $this->clientUser($globex, 'Carl C', 'client@globex.test');

        $agentDan = $this->agentUser('Dan D', 'agent1@support.test');
        $agentEve = $this->agentUser('Eve E', 'agent2@support.test');

        // Tickets ------------------------------------------------------------------------
        // On track
        $slowVms = $this->ticket($acme, $alice, 'Traag reagerende VM-omgeving', TicketStatus::InProgress, TicketPriority::High, now()->addHours(20), $agentDan);
        $expiredPassword = $this->ticket($acme, $bob, 'Wachtwoord verlopen', TicketStatus::Open, TicketPriority::Low, now()->addHours(60), $agentEve);
        $exportRequest = $this->ticket($globex, $carl, 'Aanvraag exportfunctionaliteit', TicketStatus::Open, TicketPriority::Normal, now()->addHours(40));

        // Due soon
        $prodDown = $this->ticket($acme, $alice, 'Productieomgeving is down', TicketStatus::InProgress, TicketPriority::High, now()->addHour(), $agentDan);
        $sslExpiry = $this->ticket($globex, $carl, 'SSL-certificaat verloopt binnenkort', TicketStatus::Open, TicketPriority::Normal, now()->addHours(2), $agentEve);

        // Overdue
        $invoiceError = $this->ticket($acme, $bob, 'Foutmelding bij factuurdownload', TicketStatus::InProgress, TicketPriority::Normal, now()->subHours(3));
        $apiError = $this->ticket($globex, $carl, 'Stacktrace bij API-call', TicketStatus::Open, TicketPriority::High, now()->subHours(5), $agentEve);

        // Resolved / closed (no active SLA warning)
        $licenseQuestion = $this->ticket($acme, $alice, 'Vraag over licenties', TicketStatus::Resolved, TicketPriority::Low, now()->subDay(), $agentDan);
        $migration = $this->ticket($globex, $carl, 'Migratie naar de nieuwe omgeving', TicketStatus::Closed, TicketPriority::Normal, now()->subDays(2), $agentEve);

        // Messages -----------------------------------------------------------------------
        $this->publicMessage($slowVms, $alice, 'De VM-omgeving reageert sinds vanmorgen erg traag.');
        $this->publicMessage($slowVms, $agentDan, 'Bedankt voor de melding, we onderzoeken de load op de hypervisor.');
        $this->publicMessage($prodDown, $alice, 'De productieomgeving is volledig onbereikbaar vanaf 09:00.');
        $this->publicMessage($prodDown, $agentDan, 'We hebben een storage-storing gedetecteerd en werken aan herstel.');
        $this->publicMessage($invoiceError, $bob, 'Bij het downloaden van de factuur krijg ik een 500-fout.');
        $this->publicMessage($exportRequest, $carl, 'Is het mogelijk om tickets naar CSV te exporteren?');
        $this->publicMessage($licenseQuestion, $agentDan, 'De licenties zijn verlengd tot eind van het jaar.');
    }

    private function clientUser(Organization $organization, string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'role' => UserRole::Client,
            'organization_id' => $organization->id,
        ]);
    }

    private function agentUser(string $name, string $email): User
    {
        return User::factory()->agent()->create([
            'name' => $name,
            'email' => $email,
        ]);
    }

    private function ticket(
        Organization $organization,
        User $creator,
        string $title,
        TicketStatus $status,
        TicketPriority $priority,
        \DateTimeInterface $slaDueAt,
        ?User $assignee = null,
    ): Ticket {
        return Ticket::create([
            'organization_id' => $organization->id,
            'created_by_id' => $creator->id,
            'assigned_to_id' => $assignee?->id,
            'title' => $title,
            'description' => 'Klant melding: '.$title,
            'status' => $status,
            'priority' => $priority,
            'sla_due_at' => $slaDueAt,
        ]);
    }

    private function publicMessage(Ticket $ticket, User $user, string $body): TicketMessage
    {
        return TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'type' => TicketMessageType::Public,
            'body' => $body,
        ]);
    }
}
