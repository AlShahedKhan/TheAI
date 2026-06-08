export type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    is_suspended: boolean;
    balance: number;
    total_transactions: number;
    total_purchases: number;
    created_at: string | null;
};

export type PaymentRequest = {
    id: number;
    user: { id: number; name: string; email: string } | null;
    credits: number;
    amount_bdt: number;
    payment_method: string;
    transaction_id: string;
    sender_number: string;
    status: 'pending' | 'approved' | 'rejected';
    reviewer: { id: number; name: string; email: string } | null;
    reviewed_at: string | null;
    rejection_reason: string | null;
    created_at: string | null;
};

export type CreditSettings = {
    bdt_per_credit: number;
    chat_message_cost: number;
    video_generation_cost: number;
    daily_spend_limit: number;
    daily_video_limit: number;
    payment_number: string;
    packages: number[];
};

export type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};
