import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CreditSettings } from './types';

type Props = {
    settings: CreditSettings;
};

export default function AdminCreditSettings({ settings }: Props) {
    const form = useForm({
        bdt_per_credit: settings.bdt_per_credit,
        chat_message_cost: settings.chat_message_cost,
        video_generation_cost: settings.video_generation_cost,
        daily_spend_limit: settings.daily_spend_limit,
        daily_video_limit: settings.daily_video_limit,
        payment_number: settings.payment_number,
        packages: settings.packages.join(','),
    });

    return (
        <>
            <Head title="Credit Settings" />
            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-lg font-semibold">Credit Settings</h1>
                    <p className="text-sm text-muted-foreground">
                        Changes apply to future purchases and usage only.
                    </p>
                </div>

                <form
                    className="max-w-3xl space-y-4 rounded-lg border bg-card p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch('/admin/credits/settings', {
                            preserveScroll: true,
                        });
                    }}
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        {[
                            ['bdt_per_credit', 'BDT per credit'],
                            ['chat_message_cost', 'Chat cost'],
                            ['video_generation_cost', 'Video cost'],
                            ['daily_spend_limit', 'Daily credit limit'],
                            ['daily_video_limit', 'Daily video limit'],
                        ].map(([key, label]) => (
                            <div key={key} className="grid gap-2">
                                <Label htmlFor={key}>{label}</Label>
                                <Input
                                    id={key}
                                    type="number"
                                    min={1}
                                    value={
                                        form.data[
                                            key as keyof typeof form.data
                                        ] as number
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            key as keyof typeof form.data,
                                            Number(event.target.value),
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        form.errors[
                                            key as keyof typeof form.errors
                                        ]
                                    }
                                />
                            </div>
                        ))}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="payment_number">
                            bKash/Nagad payment number
                        </Label>
                        <Input
                            id="payment_number"
                            value={form.data.payment_number}
                            onChange={(event) =>
                                form.setData(
                                    'payment_number',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.payment_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="packages">
                            Packages, comma separated
                        </Label>
                        <Input
                            id="packages"
                            value={form.data.packages}
                            onChange={(event) =>
                                form.setData('packages', event.target.value)
                            }
                        />
                        <InputError message={form.errors.packages} />
                    </div>

                    <Button disabled={form.processing}>Save settings</Button>
                </form>
            </div>
        </>
    );
}

AdminCreditSettings.layout = () => ({
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Credit Settings', href: '/admin/credits/settings' },
    ],
});
