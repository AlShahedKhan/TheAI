import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowUpRight, CreditCard, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { number, taka, usd } from './helpers';
import type { AdminUser, CreditSettings, PaymentRequest } from './types';

type Props = {
    metrics: {
        users: number;
        pending_payments: number;
        credits_sold: number;
        site_available: number;
        revenue_bdt: number;
        estimated_google_cost_usd: number;
        profit_estimate_bdt: number;
        videos_today: number;
    };
    settings: CreditSettings;
    recentPayments: PaymentRequest[];
    recentUsers: AdminUser[];
    links: {
        aiStudioSpend: string;
        cloudBilling: string;
    };
};

export default function AdminIndex({
    metrics,
    recentPayments,
    recentUsers,
    links,
}: Props) {
    const recharge = useForm({ amount_usd: 1 });
    const metricCards = [
        { label: 'Users', value: number(metrics.users), icon: Users },
        {
            label: 'Pending payments',
            value: number(metrics.pending_payments),
            icon: CreditCard,
        },
        {
            label: 'Credits sold',
            value: number(metrics.credits_sold),
            icon: CreditCard,
        },
        {
            label: 'Website pool',
            value: number(metrics.site_available),
            icon: CreditCard,
        },
        {
            label: 'Revenue',
            value: taka(metrics.revenue_bdt),
            icon: CreditCard,
        },
        {
            label: 'Google estimate',
            value: usd(metrics.estimated_google_cost_usd),
            icon: CreditCard,
        },
        {
            label: 'Profit estimate',
            value: taka(metrics.profit_estimate_bdt),
            icon: CreditCard,
        },
        {
            label: 'Videos today',
            value: number(metrics.videos_today),
            icon: CreditCard,
        },
    ];

    return (
        <>
            <Head title="Admin" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-lg font-semibold">
                            Admin Dashboard
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Operate AI credits, users, payments, and risk.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <a href={links.aiStudioSpend} target="_blank">
                                AI Studio spend
                                <ArrowUpRight />
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={links.cloudBilling} target="_blank">
                                Cloud Billing
                                <ArrowUpRight />
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <div className="flex items-center gap-2 font-medium">
                        <AlertTriangle className="size-4" />
                        Spend-cap reminder
                    </div>
                    <p className="mt-1">
                        Keep Google AI Studio monthly spend caps enabled. Google
                        cost controls can have reporting delay, so this admin
                        panel is an operational estimate.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-4">
                    {metricCards.map(({ icon: Icon, label, value }) => (
                        <div
                            key={label}
                            className="rounded-lg border bg-card p-4"
                        >
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Icon className="size-4" />
                                {label}
                            </div>
                            <div className="mt-3 text-2xl font-semibold">
                                {value}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-[1fr_22rem]">
                    <div className="rounded-lg border bg-card p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="font-medium">
                                Recent payment requests
                            </h2>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/admin/payments">View all</Link>
                            </Button>
                        </div>
                        <div className="mt-3 divide-y text-sm">
                            {recentPayments.map((payment) => (
                                <div
                                    key={payment.id}
                                    className="grid gap-2 py-3 md:grid-cols-[1fr_auto]"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {payment.user?.email}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {payment.payment_method} ·{' '}
                                            {payment.transaction_id}
                                        </div>
                                    </div>
                                    <div className="md:text-right">
                                        {number(payment.credits)} credits ·{' '}
                                        {taka(payment.amount_bdt)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-lg border bg-card p-4">
                        <h2 className="font-medium">Recharge website pool</h2>
                        <form
                            className="mt-4 space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                recharge.post('/admin/credits/recharge', {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="amount_usd">USD amount</Label>
                                <Input
                                    id="amount_usd"
                                    type="number"
                                    step={0.01}
                                    min={0.01}
                                    value={recharge.data.amount_usd}
                                    onChange={(event) =>
                                        recharge.setData(
                                            'amount_usd',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                            </div>
                            <Button disabled={recharge.processing}>
                                Add recharge
                            </Button>
                        </form>
                    </div>
                </div>

                <div className="rounded-lg border bg-card p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="font-medium">Recent users</h2>
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/admin/users">Manage users</Link>
                        </Button>
                    </div>
                    <div className="mt-3 divide-y text-sm">
                        {recentUsers.map((user) => (
                            <div
                                key={user.id}
                                className="grid gap-2 py-3 md:grid-cols-[1fr_auto]"
                            >
                                <div>
                                    <div className="font-medium">
                                        {user.name}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {user.email}
                                    </div>
                                </div>
                                <div className="md:text-right">
                                    {number(user.balance)} credits
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

AdminIndex.layout = () => ({
    breadcrumbs: [{ title: 'Admin', href: '/admin' }],
});
