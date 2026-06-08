import { Head, useForm } from '@inertiajs/react';
import { Banknote, CheckCircle, Clock, XCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Settings = {
    bdt_per_credit: number;
    chat_message_cost: number;
    video_generation_cost: number;
    payment_number: string;
    packages: number[];
};

type PurchaseRequest = {
    id: number;
    credits: number;
    amount_bdt: number;
    payment_method: string;
    transaction_id: string;
    sender_number: string;
    status: 'pending' | 'approved' | 'rejected';
    rejection_reason: string | null;
    created_at: string | null;
};

type Transaction = {
    id: number;
    type: string;
    credits: number;
    amount: number;
    currency: string;
    direction: string;
    created_at: string | null;
};

type Props = {
    balance: number;
    settings: Settings;
    paymentMethods: string[];
    requests: PurchaseRequest[];
    transactions: Transaction[];
};

function number(value: number) {
    return new Intl.NumberFormat().format(value);
}

function taka(value: number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'BDT',
        maximumFractionDigits: 0,
    }).format(value);
}

function statusIcon(status: PurchaseRequest['status']) {
    if (status === 'approved') return CheckCircle;
    if (status === 'rejected') return XCircle;
    return Clock;
}

export default function CreditsIndex({
    balance,
    settings,
    paymentMethods,
    requests,
    transactions,
}: Props) {
    const form = useForm({
        credits: settings.packages[0] ?? 100,
        payment_method: paymentMethods[0] ?? 'bkash',
        transaction_id: '',
        sender_number: '',
    });
    const amount = Number(form.data.credits || 0) * settings.bdt_per_credit;

    return (
        <>
            <Head title="Credits" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-lg font-semibold">Buy AI Credits</h1>
                    <p className="text-sm text-muted-foreground">
                        Use website credits for AI chat and video generation.
                        Credits are not Google API balance.
                    </p>
                </div>

                <div className="grid gap-4 lg:grid-cols-[1fr_24rem]">
                    <div className="rounded-lg border bg-card p-4">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Banknote className="size-4" />
                            Current balance
                        </div>
                        <div className="mt-3 text-3xl font-semibold">
                            {number(balance)}
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Chat costs {settings.chat_message_cost} credit.
                            Video costs {settings.video_generation_cost}{' '}
                            credits.
                        </p>

                        <form
                            className="mt-6 space-y-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post('/credits/purchase', {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        form.reset(
                                            'transaction_id',
                                            'sender_number',
                                        ),
                                });
                            }}
                        >
                            <div className="grid gap-3 md:grid-cols-3">
                                {settings.packages.map((credits) => (
                                    <button
                                        key={credits}
                                        type="button"
                                        className={`rounded-md border p-4 text-left transition ${
                                            Number(form.data.credits) ===
                                            credits
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'bg-background hover:bg-accent'
                                        }`}
                                        onClick={() =>
                                            form.setData('credits', credits)
                                        }
                                    >
                                        <div className="text-lg font-semibold">
                                            {number(credits)}
                                        </div>
                                        <div className="text-sm opacity-80">
                                            {taka(
                                                credits *
                                                    settings.bdt_per_credit,
                                            )}
                                        </div>
                                    </button>
                                ))}
                            </div>

                            <div className="rounded-md border bg-muted/30 p-4 text-sm">
                                Send {taka(amount)} to{' '}
                                <span className="font-semibold">
                                    {settings.payment_number}
                                </span>{' '}
                                using bKash or Nagad, then submit the
                                transaction details.
                            </div>

                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="payment_method">
                                        Method
                                    </Label>
                                    <select
                                        id="payment_method"
                                        className="h-9 rounded-md border bg-background px-3 text-sm"
                                        value={form.data.payment_method}
                                        onChange={(event) =>
                                            form.setData(
                                                'payment_method',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        {paymentMethods.map((method) => (
                                            <option key={method} value={method}>
                                                {method}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={form.errors.payment_method}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="transaction_id">
                                        Transaction ID
                                    </Label>
                                    <Input
                                        id="transaction_id"
                                        value={form.data.transaction_id}
                                        onChange={(event) =>
                                            form.setData(
                                                'transaction_id',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.transaction_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="sender_number">
                                        Sender number
                                    </Label>
                                    <Input
                                        id="sender_number"
                                        value={form.data.sender_number}
                                        onChange={(event) =>
                                            form.setData(
                                                'sender_number',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.sender_number}
                                    />
                                </div>
                            </div>

                            <Button disabled={form.processing}>
                                Submit payment request
                            </Button>
                        </form>
                    </div>

                    <div className="space-y-4">
                        <div className="rounded-lg border bg-card p-4">
                            <h2 className="font-medium">Payment requests</h2>
                            <div className="mt-3 divide-y text-sm">
                                {requests.length === 0 ? (
                                    <div className="py-4 text-muted-foreground">
                                        No payment requests yet.
                                    </div>
                                ) : (
                                    requests.map((request) => {
                                        const Icon = statusIcon(request.status);

                                        return (
                                            <div
                                                key={request.id}
                                                className="py-3"
                                            >
                                                <div className="flex items-center justify-between gap-3">
                                                    <div className="font-medium">
                                                        {number(
                                                            request.credits,
                                                        )}{' '}
                                                        credits
                                                    </div>
                                                    <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                                        <Icon className="size-3" />
                                                        {request.status}
                                                    </span>
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {taka(request.amount_bdt)} ·{' '}
                                                    {request.payment_method} ·{' '}
                                                    {request.transaction_id}
                                                </div>
                                                {request.rejection_reason && (
                                                    <div className="mt-1 text-destructive">
                                                        {
                                                            request.rejection_reason
                                                        }
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                        </div>

                        <div className="rounded-lg border bg-card p-4">
                            <h2 className="font-medium">Credit history</h2>
                            <div className="mt-3 divide-y text-sm">
                                {transactions.length === 0 ? (
                                    <div className="py-4 text-muted-foreground">
                                        No credit activity yet.
                                    </div>
                                ) : (
                                    transactions.map((transaction) => (
                                        <div
                                            key={transaction.id}
                                            className="flex items-center justify-between gap-3 py-3"
                                        >
                                            <div>
                                                <div className="font-medium">
                                                    {transaction.direction ===
                                                    'remove'
                                                        ? '-'
                                                        : '+'}
                                                    {number(
                                                        transaction.credits,
                                                    )}{' '}
                                                    credits
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {transaction.type}
                                                </div>
                                            </div>
                                            <div>
                                                {transaction.currency === 'BDT'
                                                    ? taka(transaction.amount)
                                                    : transaction.amount}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

CreditsIndex.layout = () => ({
    breadcrumbs: [
        {
            title: 'Credits',
            href: '/credits',
        },
    ],
});
