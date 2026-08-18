export type EnumValue = {
    value: string;
    label: string;
};

export type Organization = {
    id: number;
    name: string;
};

export type UserSummary = {
    id: number;
    name: string;
    email: string;
    role: EnumValue;
    organization?: Organization;
    [key: string]: unknown;
};

export type TicketMessage = {
    id: number;
    type: EnumValue;
    body: string;
    user?: UserSummary;
    created_at: string | null;
};

export type Ticket = {
    id: number;
    title: string;
    description: string;
    status: EnumValue;
    priority: EnumValue;
    sla_due_at: string | null;
    sla_status: EnumValue | null;
    organization?: Organization;
    created_by?: UserSummary;
    assigned_to?: UserSummary | null;
    messages?: TicketMessage[];
    created_at: string | null;
    updated_at: string | null;
};