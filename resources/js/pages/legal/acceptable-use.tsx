import { Head } from '@inertiajs/react';

export default function AcceptableUse() {
    return (
        <>
            <Head title="Acceptable Use" />
            <main className="mx-auto max-w-3xl space-y-6 p-6">
                <h1 className="text-2xl font-semibold">
                    Acceptable Use Policy
                </h1>
                <p>
                    Do not use this service for harmful, illegal, abusive,
                    deceptive, or unsafe content. Do not attempt to bypass
                    safety filters, rate limits, credit checks, or payment
                    controls.
                </p>
                <p>
                    Do not use generated content as professional medical, legal,
                    financial, or emergency advice. Review outputs before using
                    or sharing them.
                </p>
                <p>
                    Accounts may be suspended for abuse, fraud, duplicate or
                    false payment submissions, or excessive automated usage.
                </p>
            </main>
        </>
    );
}
