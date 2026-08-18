export type UserRole = 'client' | 'agent';

export type User = {
    id: number;
    name: string;
    email: string;
    role: {
        value: UserRole;
        label: string;
    };
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
};
