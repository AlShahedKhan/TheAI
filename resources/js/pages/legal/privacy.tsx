import { Head } from '@inertiajs/react';

export default function Privacy() {
    return (
        <>
            <Head title="Privacy" />
            <main className="mx-auto max-w-3xl space-y-6 p-6">
                <h1 className="text-2xl font-semibold">Privacy Policy</h1>
                <p>
                    We store account details, credit transactions, payment
                    request metadata, chat messages, and video generation
                    records to operate the service.
                </p>
                <p>
                    Prompts and generated content may be sent to Google Gemini
                    to produce responses. Do not submit sensitive personal,
                    confidential, medical, legal, or financial information.
                </p>
                <p>
                    We use payment request details only to verify manual
                    bKash/Nagad payments and manage credits.
                </p>
            </main>
        </>
    );
}
