import { Head } from '@inertiajs/react';

export default function RefundPolicy() {
    return (
        <>
            <Head title="Refund Policy" />
            <main className="mx-auto max-w-3xl space-y-6 p-6">
                <h1 className="text-2xl font-semibold">Refund Policy</h1>
                <p>
                    Pending payment requests can be rejected if payment cannot
                    be verified. Approved credits are generally non-refundable
                    after use.
                </p>
                <p>
                    If a manual payment was sent to the wrong amount or cannot
                    be matched, contact support with your transaction ID, sender
                    number, method, and amount.
                </p>
                <p>
                    Admins may manually adjust credits when correcting verified
                    payment or account issues.
                </p>
            </main>
        </>
    );
}
