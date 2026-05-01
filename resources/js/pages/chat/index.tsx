import { Form, Head, Link } from '@inertiajs/react';
import {
    Edit3,
    LoaderCircle,
    MessageCircle,
    Plus,
    SendHorizontal,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Conversation = {
    id: string;
    title: string;
    updated_at?: string;
};

type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    created_at: string;
};

type Props = {
    conversations: Conversation[];
    activeConversation: Conversation | null;
    messages: ChatMessage[];
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

export default function ChatIndex({
    conversations,
    activeConversation,
    messages,
}: Props) {
    const [renameOpen, setRenameOpen] = useState(false);
    const pageTitle = activeConversation?.title ?? 'New chat';

    return (
        <>
            <Head title={pageTitle} />

            <div className="flex h-[calc(100vh-5rem)] overflow-hidden">
                <aside className="hidden w-72 shrink-0 flex-col border-r bg-muted/20 md:flex">
                    <div className="border-b p-3">
                        <Button className="w-full justify-start" asChild>
                            <Link href="/chat?new=1">
                                <Plus />
                                New Chat
                            </Link>
                        </Button>
                    </div>

                    <div className="flex-1 overflow-y-auto p-2">
                        {conversations.length === 0 ? (
                            <div className="px-3 py-8 text-center text-sm text-muted-foreground">
                                Your chats will appear here.
                            </div>
                        ) : (
                            <div className="space-y-1">
                                {conversations.map((conversation) => (
                                    <Button
                                        key={conversation.id}
                                        variant={
                                            activeConversation?.id ===
                                            conversation.id
                                                ? 'secondary'
                                                : 'ghost'
                                        }
                                        className="h-auto w-full justify-start px-3 py-2 text-left"
                                        asChild
                                    >
                                        <Link href={`/chat/${conversation.id}`}>
                                            <MessageCircle className="mt-0.5" />
                                            <span className="min-w-0 flex-1 truncate">
                                                {conversation.title}
                                            </span>
                                        </Link>
                                    </Button>
                                ))}
                            </div>
                        )}
                    </div>
                </aside>

                <main className="flex min-w-0 flex-1 flex-col overflow-hidden">
                    <div className="flex items-center justify-between gap-3 border-b px-4 py-3">
                        <div className="min-w-0">
                            <h1 className="truncate text-lg font-semibold">
                                {pageTitle}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {activeConversation
                                    ? 'Continue your Gemini conversation.'
                                    : 'Send a message to start a new chat.'}
                            </p>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                className="md:hidden"
                                variant="outline"
                                size="sm"
                                asChild
                            >
                                <Link href="/chat?new=1">
                                    <Plus />
                                    New
                                </Link>
                            </Button>

                            {activeConversation && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setRenameOpen(true)}
                                >
                                    <Edit3 />
                                    Rename
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="flex-1 overflow-y-auto px-4 py-6">
                        <div className="mx-auto flex max-w-3xl flex-col gap-4">
                            {messages.length === 0 && (
                                <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    Send a message to start testing your Gemini
                                    agent.
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
                                    {activeConversation && (
                                        <input
                                            type="hidden"
                                            name="conversation_id"
                                            value={activeConversation.id}
                                        />
                                    )}
                                    <div className="flex-1">
                                        <textarea
                                            name="message"
                                            rows={2}
                                            placeholder="Message Gemini..."
                                            className="min-h-12 w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        />
                                        <InputError message={errors.message} />
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
                </main>
            </div>

            {activeConversation && (
                <Dialog open={renameOpen} onOpenChange={setRenameOpen}>
                    <DialogContent>
                        <Form
                            action={`/chat/${activeConversation.id}`}
                            method="patch"
                            className="space-y-6"
                            onSuccess={() => setRenameOpen(false)}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <DialogHeader>
                                        <DialogTitle>Rename chat</DialogTitle>
                                    </DialogHeader>

                                    <div className="grid gap-2">
                                        <Label htmlFor="title">Title</Label>
                                        <Input
                                            id="title"
                                            name="title"
                                            defaultValue={
                                                activeConversation.title
                                            }
                                            maxLength={100}
                                            required
                                        />
                                        <InputError message={errors.title} />
                                    </div>

                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={() => setRenameOpen(false)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            )}
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
