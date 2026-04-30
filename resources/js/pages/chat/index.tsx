import { Form, Head } from '@inertiajs/react';
import { LoaderCircle, SendHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';

type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    created_at: string;
};

function displayContent(content: string) {
    try {
        const parsed = JSON.parse(content) as {
            feedback?: string;
            score?: number;
        };

        if (parsed.feedback) {
            return (
                <div className="space-y-2">
                    <p>{parsed.feedback}</p>
                    {parsed.score && (
                        <p className="text-xs font-medium text-muted-foreground">
                            Score: {parsed.score}/10
                        </p>
                    )}
                </div>
            );
        }
    } catch {
        // Plain text response.
    }

    return <p className="whitespace-pre-wrap">{content}</p>;
}

export default function ChatIndex({ messages }: { messages: ChatMessage[] }) {
    return (
        <>
            <Head title="Gemini Chat" />

            <div className="flex h-[calc(100vh-5rem)] flex-col overflow-hidden">
                <div className="border-b px-4 py-3">
                    <h1 className="text-lg font-semibold">Gemini Chat</h1>
                    <p className="text-sm text-muted-foreground">
                        Test your Laravel AI SDK agent from the browser.
                    </p>
                </div>

                <div className="flex-1 overflow-y-auto px-4 py-6">
                    <div className="mx-auto flex max-w-3xl flex-col gap-4">
                        {messages.length === 0 && (
                            <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                Send a message to start testing your Gemini agent.
                            </div>
                        )}

                        {messages.map((message) => (
                            <div
                                key={message.id}
                                className={`flex ${
                                    message.role === 'user'
                                        ? 'justify-end'
                                        : 'justify-start'
                                }`}
                            >
                                <div
                                    className={`max-w-[85%] rounded-lg px-4 py-3 text-sm leading-6 ${
                                        message.role === 'user'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'border bg-card text-card-foreground'
                                    }`}
                                >
                                    {displayContent(message.content)}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="border-t bg-background px-4 py-4">
                    <Form
                        action="/chat"
                        method="post"
                        resetOnSuccess={['message']}
                        className="mx-auto flex max-w-3xl gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="flex-1">
                                    <textarea
                                        name="message"
                                        rows={2}
                                        placeholder="Message Gemini..."
                                        className="min-h-12 w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    />
                                    {errors.message && (
                                        <p className="mt-1 text-sm text-destructive">
                                            {errors.message}
                                        </p>
                                    )}
                                </div>
                                <Button
                                    type="submit"
                                    size="icon"
                                    className="mt-1"
                                    disabled={processing}
                                >
                                    {processing ? (
                                        <LoaderCircle className="animate-spin" />
                                    ) : (
                                        <SendHorizontal />
                                    )}
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

ChatIndex.layout = () => ({
    breadcrumbs: [
        {
            title: 'Chat',
            href: '/chat',
        },
    ],
});
