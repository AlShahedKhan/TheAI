import { Head } from '@inertiajs/react';

export default function Terms() {
    return (
        <>
            <Head title="Terms" />
            <main className="mx-auto max-w-3xl space-y-6 p-6">
                <h1 className="text-2xl font-semibold">Terms of Service</h1>
                <p>
                    This website sells AI credits for using our chat and video
                    tools. Credits are website credits, not Google API keys,
                    Google account balance, or direct Google API resale.
                </p>
                <p>
                    You must be at least 18 years old and use this service for
                    professional, educational, or business purposes. You are
                    responsible for prompts, uploaded content, generated output,
                    and complying with applicable law.
                </p>
                <p>
                    Our tools are powered by Google Gemini. Availability, model
                    behavior, pricing, and rate limits may change. We may
                    suspend accounts that abuse the service or attempt to bypass
                    safety, payment, or credit controls.
                </p>
            </main>
        </>
    );
}
