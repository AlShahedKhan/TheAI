import { Head, Link } from '@inertiajs/react';
import {
    Banknote,
    Clapperboard,
    CreditCard,
    MessageCircle,
    ShieldCheck,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type UserSummary = {
    pending_payments: number;
    chat_replies: number;
    videos: number;
    credits_used: number;
};

type AdminSummary = {
    pending_payments: number;
    credits_sold: number;
    site_available: number;
    revenue_bdt: number;
    estimated_google_cost_usd: number;
    profit_estimate_bdt: number;
};

type Props = {
    userSummary: UserSummary;
    adminSummary: AdminSummary | null;
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

function usd(value: number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 4,
    }).format(value);
}

export default function Dashboard({ adminSummary, userSummary }: Props) {
    const userCards = [
        {
            label: 'Pending payments',
            value: number(userSummary.pending_payments),
            detail: 'Waiting for admin approval',
            icon: CreditCard,
        },
        {
            label: 'Chat replies',
            value: number(userSummary.chat_replies),
            detail: `${number(userSummary.credits_used)} credits used`,
            icon: MessageCircle,
        },
        {
            label: 'Videos',
            value: number(userSummary.videos),
            detail: 'Text to video generations',
            icon: Clapperboard,
        },
    ];

    const adminCards = adminSummary
        ? [
              {
                  label: 'Pending payments',
                  value: number(adminSummary.pending_payments),
                  detail: 'Needs review',
                  icon: CreditCard,
              },
              {
                  label: 'Website pool',
                  value: number(adminSummary.site_available),
                  detail: `${number(adminSummary.credits_sold)} credits sold`,
                  icon: Banknote,
              },
              {
                  label: 'Revenue',
                  value: taka(adminSummary.revenue_bdt),
                  detail: `${usd(adminSummary.estimated_google_cost_usd)} Google estimate`,
                  icon: CreditCard,
              },
              {
                  label: 'Profit estimate',
                  value: taka(adminSummary.profit_estimate_bdt),
                  detail: 'After estimated Google cost',
                  icon: ShieldCheck,
              },
          ]
        : [];

    return (
        <>
            <Head title="Dashboard" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-lg font-semibold">Dashboard</h1>
                        <p className="text-sm text-muted-foreground">
                            Quick activity and the next things that need action.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/chat?new=1">Start chat</Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/credits">Buy credits</Link>
                        </Button>
                        {adminSummary ? (
                            <Button asChild>
                                <Link href="/admin">Admin dashboard</Link>
                            </Button>
                        ) : null}
                    </div>
                </div>

                <section className="space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            My activity
                        </h2>
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/chat">Chats</Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/videos">Videos</Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/credits/history">History</Link>
                            </Button>
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        {userCards.map(
                            ({ detail, icon: Icon, label, value }) => (
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
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {detail}
                                    </p>
                                </div>
                            ),
                        )}
                    </div>
                </section>

                {adminSummary ? (
                    <section className="space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="text-sm font-medium text-muted-foreground">
                                Admin shortcuts
                            </h2>
                            <div className="flex gap-2">
                                <Button variant="outline" size="sm" asChild>
                                    <Link href="/admin/payments">
                                        Payment queue
                                    </Link>
                                </Button>
                                <Button size="sm" asChild>
                                    <Link href="/admin">Admin dashboard</Link>
                                </Button>
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-4">
                            {adminCards.map(
                                ({ detail, icon: Icon, label, value }) => (
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
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {detail}
                                        </p>
                                    </div>
                                ),
                            )}
                        </div>
                    </section>
                ) : null}
            </div>
        </>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
