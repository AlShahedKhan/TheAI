import { Head } from '@inertiajs/react';
import { ArrowUpRight, CreditCard, MessageSquare, Video } from 'lucide-react';
import { Button } from '@/components/ui/button';

type ModelUsage = {
    model: string;
    label: string;
    messages: number;
    prompt_tokens: number;
    completion_tokens: number;
    estimated_cost: number;
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
    budget: {
        configured: boolean;
        amount: number | null;
        remaining: number | null;
    };
    links: {
        aiStudio: string;
        cloudBilling: string;
        pricing: string;
    };
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

function number(value: number) {
    return new Intl.NumberFormat().format(value);
}

export default function UsageIndex({ chat, video, budget, links }: Props) {
    return (
        <>
            <Head title="Usage" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-lg font-semibold">Usage & Billing</h1>
                    <p className="text-sm text-muted-foreground">
                        Estimated website usage for this month. Check Google
                        Billing for the exact remaining credit or invoice.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
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
                            <CreditCard className="size-4" />
                            Remaining app budget
                        </div>
                        <div className="mt-3 text-2xl font-semibold">
                            {money(budget.remaining)}
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Set `GEMINI_MONTHLY_BUDGET_USD` to enable this.
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
                            <h2 className="font-medium">
                                Exact Google balance
                            </h2>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Google does not expose a simple Gemini wallet
                                balance to this app by default. Use these links
                                for official remaining credits and invoices.
                            </p>
                            <div className="mt-4 flex flex-col gap-2">
                                <Button variant="outline" asChild>
                                    <a href={links.aiStudio} target="_blank">
                                        Google AI Studio
                                        <ArrowUpRight />
                                    </a>
                                </Button>
                                <Button variant="outline" asChild>
                                    <a
                                        href={links.cloudBilling}
                                        target="_blank"
                                    >
                                        Google Cloud Billing
                                        <ArrowUpRight />
                                    </a>
                                </Button>
                                <Button variant="outline" asChild>
                                    <a href={links.pricing} target="_blank">
                                        Gemini pricing
                                        <ArrowUpRight />
                                    </a>
                                </Button>
                            </div>
                        </div>
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
