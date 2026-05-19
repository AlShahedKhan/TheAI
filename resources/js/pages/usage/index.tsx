import { Head, useForm } from '@inertiajs/react';
import {
    ArrowUpRight,
    Banknote,
    CreditCard,
    Landmark,
    MessageSquare,
    Video,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ModelUsage = {
    model: string;
    label: string;
    messages: number;
    prompt_tokens: number;
    completion_tokens: number;
    estimated_cost: number;
};

type CreditTransaction = {
    id: number;
    type: string;
    credits: number;
    amount: number;
    currency: string;
    created_at: string | null;
    user: {
        name: string;
        email: string;
    } | null;
};

type Props = {
    chat: {
        messages: number;
        prompt_tokens: number;
        completion_tokens: number;
        estimated_cost: number;
        by_model: ModelUsage[];
    };
    video: {
        total: number;
        completed: number;
        processing: number;
        failed: number;
    };
    credits: {
        is_admin: boolean;
        rates: {
            credits_per_usd: number;
            bdt_per_credit: number;
            chat_message_cost: number;
            video_generation_cost: number;
        };
        user: {
            balance: number;
            purchased: number;
            used: number;
            spent_bdt: number;
            recent: CreditTransaction[];
        };
        site: {
            admin_recharged: number;
            sold: number;
            available: number;
            recharged_usd: number;
            sales_bdt: number;
            recent: CreditTransaction[];
        };
    };
    budget: {
        configured: boolean;
        amount: number | null;
        remaining: number | null;
    };
    links: {
        aiStudio: string;
        cloudBilling: string;
        pricing: string;
    } | null;
};

function money(value: number | null) {
    if (value === null) {
        return 'Not set';
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 4,
    }).format(value);
}

function taka(value: number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'BDT',
        maximumFractionDigits: 0,
    }).format(value);
}

function number(value: number) {
    return new Intl.NumberFormat().format(value);
}

function transactionLabel(type: string) {
    return type === 'admin_recharge' ? 'Admin recharge' : 'User purchase';
}

export default function UsageIndex({
    chat,
    video,
    credits,
    budget,
    links,
}: Props) {
    const purchaseForm = useForm({ credits: 100 });
    const rechargeForm = useForm({ amount_usd: 1 });
    const purchaseCredits = Number(purchaseForm.data.credits || 0);
    const rechargeUsd = Number(rechargeForm.data.amount_usd || 0);

    return (
        <>
            <Head title="Usage" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-lg font-semibold">Usage & Billing</h1>
                    <p className="text-sm text-muted-foreground">
                        Credit balance, dummy purchases, and estimated Google AI
                        usage for this month.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-lg border bg-card p-4">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Banknote className="size-4" />
                            My credits
                        </div>
                        <div className="mt-3 text-2xl font-semibold">
                            {number(credits.user.balance)}
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Chat costs {number(credits.rates.chat_message_cost)}{' '}
                            credit. Video costs{' '}
                            {number(credits.rates.video_generation_cost)}.
                        </p>
                    </div>

                    {credits.is_admin ? (
                        <div className="rounded-lg border bg-card p-4">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Landmark className="size-4" />
                                Website pool
                            </div>
                            <div className="mt-3 text-2xl font-semibold">
                                {number(credits.site.available)}
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                $1 admin recharge ={' '}
                                {number(credits.rates.credits_per_usd)} credits.
                            </p>
                        </div>
                    ) : null}

                    <div className="rounded-lg border bg-card p-4">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <CreditCard className="size-4" />
                            Estimated chat spend
                        </div>
                        <div className="mt-3 text-2xl font-semibold">
                            {money(chat.estimated_cost)}
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Based on saved token usage in this app.
                        </p>
                    </div>

                    <div className="rounded-lg border bg-card p-4">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <MessageSquare className="size-4" />
                            Chat tokens
                        </div>
                        <div className="mt-3 text-2xl font-semibold">
                            {number(
                                chat.prompt_tokens + chat.completion_tokens,
                            )}
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {number(chat.prompt_tokens)} input /{' '}
                            {number(chat.completion_tokens)} output
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[1fr_22rem]">
                    <div className="rounded-lg border bg-card p-4">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <CreditCard className="size-4" />
                            Buy credits
                        </div>
                        <form
                            className="mt-4 grid gap-4 md:grid-cols-[1fr_auto]"
                            onSubmit={(event) => {
                                event.preventDefault();
                                purchaseForm.post('/usage/credits/purchase', {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="credits">Credits</Label>
                                <Input
                                    id="credits"
                                    min={1}
                                    step={1}
                                    type="number"
                                    value={purchaseForm.data.credits}
                                    onChange={(event) =>
                                        purchaseForm.setData(
                                            'credits',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                                {purchaseForm.errors.credits ? (
                                    <p className="text-sm text-destructive">
                                        {purchaseForm.errors.credits}
                                    </p>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {number(purchaseCredits)} credits costs{' '}
                                        {taka(
                                            purchaseCredits *
                                                credits.rates.bdt_per_credit,
                                        )}
                                        .
                                    </p>
                                )}
                            </div>
                            <Button
                                className="self-end"
                                disabled={purchaseForm.processing}
                            >
                                Buy dummy credits
                            </Button>
                        </form>
                    </div>

                    <div className="rounded-lg border bg-card p-4">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <CreditCard className="size-4" />
                            My purchase summary
                        </div>
                        <div className="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <div className="text-muted-foreground">
                                    Purchased
                                </div>
                                <div className="font-semibold">
                                    {number(credits.user.purchased)}
                                </div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">
                                    Used
                                </div>
                                <div className="font-semibold">
                                    {number(credits.user.used)}
                                </div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">
                                    Paid
                                </div>
                                <div className="font-semibold">
                                    {taka(credits.user.spent_bdt)}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {credits.is_admin ? (
                    <div className="grid gap-4 lg:grid-cols-[1fr_22rem]">
                        <div className="rounded-lg border bg-card p-4">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Landmark className="size-4" />
                                Admin recharge
                            </div>
                            <form
                                className="mt-4 grid gap-4 md:grid-cols-[1fr_auto]"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    rechargeForm.post(
                                        '/usage/credits/recharge',
                                        {
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="amount_usd">
                                        Google AI Studio amount
                                    </Label>
                                    <Input
                                        id="amount_usd"
                                        min={0.01}
                                        step={0.01}
                                        type="number"
                                        value={rechargeForm.data.amount_usd}
                                        onChange={(event) =>
                                            rechargeForm.setData(
                                                'amount_usd',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                    {rechargeForm.errors.amount_usd ? (
                                        <p className="text-sm text-destructive">
                                            {rechargeForm.errors.amount_usd}
                                        </p>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            {money(rechargeUsd)} adds{' '}
                                            {number(
                                                rechargeUsd *
                                                    credits.rates
                                                        .credits_per_usd,
                                            )}{' '}
                                            website credits.
                                        </p>
                                    )}
                                </div>
                                <Button
                                    className="self-end"
                                    disabled={rechargeForm.processing}
                                >
                                    Add dummy recharge
                                </Button>
                            </form>
                        </div>

                        <div className="rounded-lg border bg-card p-4">
                            <div className="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <div className="text-muted-foreground">
                                        Recharged
                                    </div>
                                    <div className="font-semibold">
                                        {number(credits.site.admin_recharged)}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground">
                                        Sold
                                    </div>
                                    <div className="font-semibold">
                                        {number(credits.site.sold)}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground">
                                        Google spend
                                    </div>
                                    <div className="font-semibold">
                                        {money(credits.site.recharged_usd)}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground">
                                        User sales
                                    </div>
                                    <div className="font-semibold">
                                        {taka(credits.site.sales_bdt)}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-[1fr_22rem]">
                    <div className="rounded-lg border bg-card">
                        <div className="border-b px-4 py-3">
                            <h2 className="font-medium">Chat by model</h2>
                        </div>
                        <div className="divide-y">
                            {chat.by_model.length === 0 ? (
                                <div className="p-4 text-sm text-muted-foreground">
                                    No billed chat usage saved this month.
                                </div>
                            ) : (
                                chat.by_model.map((model) => (
                                    <div
                                        key={model.model}
                                        className="grid gap-3 p-4 md:grid-cols-[1fr_auto]"
                                    >
                                        <div>
                                            <div className="font-medium">
                                                {model.label}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {model.model}
                                            </div>
                                        </div>
                                        <div className="text-sm md:text-right">
                                            <div>
                                                {money(model.estimated_cost)}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {number(model.messages)} replies
                                                ·{' '}
                                                {number(
                                                    model.prompt_tokens +
                                                        model.completion_tokens,
                                                )}{' '}
                                                tokens
                                            </div>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div className="rounded-lg border bg-card p-4">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Video className="size-4" />
                                Video generations
                            </div>
                            <div className="mt-3 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <div className="text-muted-foreground">
                                        Total
                                    </div>
                                    <div className="font-semibold">
                                        {number(video.total)}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground">
                                        Completed
                                    </div>
                                    <div className="font-semibold">
                                        {number(video.completed)}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground">
                                        Processing
                                    </div>
                                    <div className="font-semibold">
                                        {number(video.processing)}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground">
                                        Failed
                                    </div>
                                    <div className="font-semibold">
                                        {number(video.failed)}
                                    </div>
                                </div>
                            </div>
                            <p className="mt-3 text-sm text-muted-foreground">
                                Veo cost depends on generated seconds. Use
                                Google Billing for exact video charges.
                            </p>
                        </div>

                        <div className="rounded-lg border bg-card p-4">
                            <h2 className="font-medium">Credit history</h2>
                            <div className="mt-3 divide-y text-sm">
                                {credits.user.recent.length === 0 ? (
                                    <div className="py-3 text-muted-foreground">
                                        No credit purchases yet.
                                    </div>
                                ) : (
                                    credits.user.recent.map((transaction) => (
                                        <div
                                            className="flex items-center justify-between gap-3 py-3"
                                            key={transaction.id}
                                        >
                                            <div>
                                                <div className="font-medium">
                                                    {number(
                                                        transaction.credits,
                                                    )}{' '}
                                                    credits
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {transactionLabel(
                                                        transaction.type,
                                                    )}
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                {transaction.currency === 'BDT'
                                                    ? taka(transaction.amount)
                                                    : money(transaction.amount)}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>

                        {credits.is_admin ? (
                            <div className="rounded-lg border bg-card p-4">
                                <h2 className="font-medium">Admin ledger</h2>
                                <div className="mt-3 divide-y text-sm">
                                    {credits.site.recent.length === 0 ? (
                                        <div className="py-3 text-muted-foreground">
                                            No site credit activity yet.
                                        </div>
                                    ) : (
                                        credits.site.recent.map(
                                            (transaction) => (
                                                <div
                                                    className="grid gap-2 py-3"
                                                    key={transaction.id}
                                                >
                                                    <div className="flex items-center justify-between gap-3">
                                                        <div className="font-medium">
                                                            {transactionLabel(
                                                                transaction.type,
                                                            )}
                                                        </div>
                                                        <div>
                                                            {number(
                                                                transaction.credits,
                                                            )}{' '}
                                                            credits
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center justify-between gap-3 text-muted-foreground">
                                                        <div>
                                                            {transaction.user
                                                                ? transaction
                                                                      .user
                                                                      .email
                                                                : 'Website pool'}
                                                        </div>
                                                        <div>
                                                            {transaction.currency ===
                                                            'BDT'
                                                                ? taka(
                                                                      transaction.amount,
                                                                  )
                                                                : money(
                                                                      transaction.amount,
                                                                  )}
                                                        </div>
                                                    </div>
                                                </div>
                                            ),
                                        )
                                    )}
                                </div>
                            </div>
                        ) : null}

                        {credits.is_admin ? (
                            <div className="rounded-lg border bg-card p-4">
                                <h2 className="font-medium">
                                    Exact Google balance
                                </h2>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Google does not expose a simple Gemini
                                    wallet balance to this app by default. Use
                                    these links for official remaining credits
                                    and invoices.
                                </p>
                                <div className="mt-4 flex flex-col gap-2">
                                    <Button variant="outline" asChild>
                                        <a
                                            href={links?.aiStudio}
                                            target="_blank"
                                        >
                                            Google AI Studio
                                            <ArrowUpRight />
                                        </a>
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <a
                                            href={links?.cloudBilling}
                                            target="_blank"
                                        >
                                            Google Cloud Billing
                                            <ArrowUpRight />
                                        </a>
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <a
                                            href={links?.pricing}
                                            target="_blank"
                                        >
                                            Gemini pricing
                                            <ArrowUpRight />
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        ) : null}
                    </div>
                </div>
            </div>
        </>
    );
}

UsageIndex.layout = () => ({
    breadcrumbs: [
        {
            title: 'Usage',
            href: '/usage',
        },
    ],
});
