import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { taka } from './helpers';
import type { Paginated, PaymentRequest } from './types';

type Props = {
    payments: Paginated<PaymentRequest>;
};

export default function AdminPayments({ payments }: Props) {
    const rejectForm = useForm({ rejection_reason: '' });

    return (
        <>
            <Head title="Payment Queue" />
            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-lg font-semibold">Payment Queue</h1>
                    <p className="text-sm text-muted-foreground">
                        Approve manual bKash/Nagad requests after checking the
                        transaction.
                    </p>
                </div>

                <div className="rounded-lg border bg-card">
                    <div className="divide-y">
                        {payments.data.map((payment) => (
                            <div
                                key={payment.id}
                                className="grid gap-4 p-4 lg:grid-cols-[1fr_22rem]"
                            >
                                <div className="space-y-1 text-sm">
                                    <div className="font-medium">
                                        {payment.user?.name} ·{' '}
                                        {payment.user?.email}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {payment.payment_method} from{' '}
                                        {payment.sender_number}
                                    </div>
                                    <div>
                                        Transaction: {payment.transaction_id}
                                    </div>
                                    <div>
                                        {payment.credits} credits ·{' '}
                                        {taka(payment.amount_bdt)}
                                    </div>
                                    <div className="text-muted-foreground">
                                        Status: {payment.status}
                                    </div>
                                    {payment.rejection_reason && (
                                        <div className="text-destructive">
                                            {payment.rejection_reason}
                                        </div>
                                    )}
                                </div>

                                {payment.status === 'pending' ? (
                                    <div className="space-y-3">
                                        <form
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                rejectForm.post(
                                                    `/admin/payments/${payment.id}/approve`,
                                                    {
                                                        preserveScroll: true,
                                                    },
                                                );
                                            }}
                                        >
                                            <Button className="w-full">
                                                Approve and add credits
                                            </Button>
                                        </form>

                                        <form
                                            className="space-y-2"
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                rejectForm.post(
                                                    `/admin/payments/${payment.id}/reject`,
                                                    {
                                                        preserveScroll: true,
                                                        onSuccess: () =>
                                                            rejectForm.reset(),
                                                    },
                                                );
                                            }}
                                        >
                                            <Input
                                                placeholder="Rejection reason"
                                                value={
                                                    rejectForm.data
                                                        .rejection_reason
                                                }
                                                onChange={(event) =>
                                                    rejectForm.setData(
                                                        'rejection_reason',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={
                                                    rejectForm.errors
                                                        .rejection_reason
                                                }
                                            />
                                            <Button
                                                className="w-full"
                                                variant="outline"
                                                disabled={rejectForm.processing}
                                            >
                                                Reject
                                            </Button>
                                        </form>
                                    </div>
                                ) : (
                                    <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                                        Reviewed by{' '}
                                        {payment.reviewer?.email ?? 'admin'}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

AdminPayments.layout = () => ({
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Payments', href: '/admin/payments' },
    ],
});
