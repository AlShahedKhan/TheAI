import { Form, Head, Link } from '@inertiajs/react';
import {
    Bot,
    Check,
    ChevronDown,
    Copy,
    Edit3,
    LoaderCircle,
    MessageCircle,
    Plus,
    RefreshCcw,
    SendHorizontal,
    Sparkles,
    User,
    Zap,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
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
import { cn } from '@/lib/utils';

type Conversation = {
    id: string;
    title: string;
    model: string;
    updated_at?: string;
};

type ModelOption = {
    value: string;
    label: string;
    description: string;
};

type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    model_label?: string | null;
    created_at: string;
};

type Props = {
    conversations: Conversation[];
    activeConversation: Conversation | null;
    modelOptions: ModelOption[];
    defaultModel: string;
    messages: ChatMessage[];
};

const starterPrompts = [
    'Summarize this project and suggest the next feature.',
    'Write a clean Laravel controller for a small CRUD flow.',
    'Explain this error and give me the safest fix.',
];

function formattedTime(value: string) {
    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function modelBadge(value: string) {
    if (value.includes('pro')) {
        return { label: 'Deep', icon: Sparkles };
    }

    if (value.includes('lite')) {
        return { label: 'Efficient', icon: Zap };
    }

    return { label: 'Balanced', icon: MessageCircle };
}

function MarkdownLite({ content }: { content: string }) {
    const sections = content.split(/```/g);

    return (
        <div className="space-y-3">
            {sections.map((section, index) => {
                if (index % 2 === 1) {
                    const [firstLine, ...rest] = section.split('\n');
                    const language = firstLine.trim();
                    const code = rest.join('\n').trim() || firstLine;

                    return (
                        <pre
                            key={`${index}-${code.slice(0, 16)}`}
                            className="overflow-x-auto rounded-md border bg-muted p-3 text-xs leading-5 text-foreground"
                        >
                            {language && (
                                <div className="mb-2 text-[11px] font-medium text-muted-foreground uppercase">
                                    {language}
                                </div>
                            )}
                            <code>{code}</code>
                        </pre>
                    );
                }

                return section
                    .split(/\n{2,}/g)
                    .filter(Boolean)
                    .map((paragraph, paragraphIndex) => (
                        <p
                            key={`${index}-${paragraphIndex}`}
                            className="text-sm leading-6 whitespace-pre-wrap"
                        >
                            {paragraph}
                        </p>
                    ));
            })}
        </div>
    );
}

function MessageRow({
    message,
    copied,
    onRegenerate,
    onCopy,
}: {
    message: ChatMessage;
    copied: boolean;
    onRegenerate?: () => void;
    onCopy: () => void;
}) {
    const isUser = message.role === 'user';
    const Icon = isUser ? User : Bot;

    return (
        <div className={cn('group flex gap-3', isUser && 'justify-end')}>
            {!isUser && (
                <div className="mt-1 flex size-8 shrink-0 items-center justify-center rounded-md border bg-card">
                    <Icon className="size-4" />
                </div>
            )}

            <div className={cn('max-w-[82%] min-w-0', !isUser && 'max-w-3xl')}>
                <div
                    className={cn(
                        'rounded-lg px-4 py-3 shadow-xs',
                        isUser
                            ? 'bg-primary text-primary-foreground'
                            : 'border bg-card text-card-foreground',
                    )}
                >
                    <MarkdownLite content={message.content} />
                </div>
                <div
                    className={cn(
                        'mt-1 flex items-center gap-2 text-xs text-muted-foreground opacity-80',
                        isUser && 'justify-end',
                    )}
                >
                    <span>
                        {isUser ? 'You' : (message.model_label ?? 'Gemini')}
                    </span>
                    <span>{formattedTime(message.created_at)}</span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-7 px-2 opacity-0 transition-opacity group-hover:opacity-100"
                        onClick={onCopy}
                    >
                        {copied ? <Check /> : <Copy />}
                        {copied ? 'Copied' : 'Copy'}
                    </Button>
                    {!isUser && onRegenerate && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-7 px-2 opacity-0 transition-opacity group-hover:opacity-100"
                            onClick={onRegenerate}
                        >
                            <RefreshCcw />
                            Regenerate
                        </Button>
                    )}
                </div>
            </div>

            {isUser && (
                <div className="mt-1 flex size-8 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground">
                    <Icon className="size-4" />
                </div>
            )}
        </div>
    );
}

export default function ChatIndex({
    conversations,
    activeConversation,
    modelOptions,
    defaultModel,
    messages,
}: Props) {
    const [renameOpen, setRenameOpen] = useState(false);
    const [draft, setDraft] = useState('');
    const [selectedModel, setSelectedModel] = useState(
        activeConversation?.model ?? defaultModel,
    );
    const [regenerateMessage, setRegenerateMessage] =
        useState<ChatMessage | null>(null);
    const [regenerateModel, setRegenerateModel] = useState(
        activeConversation?.model ?? defaultModel,
    );
    const [copiedMessageId, setCopiedMessageId] = useState<string | null>(null);
    const bottomRef = useRef<HTMLDivElement>(null);
    const pageTitle = activeConversation?.title ?? 'New chat';
    const activeModel = useMemo(
        () =>
            modelOptions.find((model) => model.value === selectedModel) ??
            modelOptions[0],
        [modelOptions, selectedModel],
    );

    useEffect(() => {
        setSelectedModel(activeConversation?.model ?? defaultModel);
        setRegenerateModel(activeConversation?.model ?? defaultModel);
        setDraft('');
    }, [activeConversation?.id, activeConversation?.model, defaultModel]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages.length]);

    const copyMessage = async (message: ChatMessage) => {
        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(message.content);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = message.content;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
        } catch {
            const textarea = document.createElement('textarea');
            textarea.value = message.content;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }

        setCopiedMessageId(message.id);
        window.setTimeout(() => setCopiedMessageId(null), 1500);
    };

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
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate">
                                                    {conversation.title}
                                                </span>
                                                <span className="block truncate text-xs font-normal text-muted-foreground">
                                                    {conversation.model}
                                                </span>
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
                            <p className="truncate text-sm text-muted-foreground">
                                {activeModel
                                    ? `${activeModel.label} - ${activeModel.description}`
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
                        <div className="mx-auto flex max-w-4xl flex-col gap-5">
                            {messages.length === 0 && (
                                <div className="space-y-5 rounded-lg border border-dashed p-6 text-center">
                                    <div>
                                        <h2 className="text-base font-semibold">
                                            Start with a sharp prompt
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Choose a model, ask anything, and
                                            this chat will save automatically.
                                        </p>
                                    </div>
                                    <div className="grid gap-2 md:grid-cols-3">
                                        {starterPrompts.map((prompt) => (
                                            <Button
                                                key={prompt}
                                                type="button"
                                                variant="outline"
                                                className="h-auto justify-start px-3 py-3 text-left text-sm whitespace-normal"
                                                onClick={() => setDraft(prompt)}
                                            >
                                                {prompt}
                                            </Button>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {messages.map((message) => (
                                <MessageRow
                                    key={message.id}
                                    message={message}
                                    copied={copiedMessageId === message.id}
                                    onRegenerate={
                                        message.role === 'assistant'
                                            ? () => {
                                                  setRegenerateMessage(message);
                                                  setRegenerateModel(
                                                      selectedModel,
                                                  );
                                              }
                                            : undefined
                                    }
                                    onCopy={() => copyMessage(message)}
                                />
                            ))}

                            <div ref={bottomRef} />
                        </div>
                    </div>

                    <div className="border-t bg-background px-4 py-4">
                        <Form
                            action="/chat"
                            method="post"
                            className="mx-auto max-w-4xl space-y-3"
                            onSuccess={() => setDraft('')}
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
                                    <input
                                        type="hidden"
                                        name="model"
                                        value={selectedModel}
                                    />

                                    <div className="flex gap-2">
                                        <div className="flex flex-1 overflow-hidden rounded-md border border-input shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
                                            <div className="relative shrink-0 border-r bg-background">
                                                <select
                                                    value={selectedModel}
                                                    onChange={(event) =>
                                                        setSelectedModel(
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="h-full min-h-16 max-w-36 appearance-none bg-transparent py-2 pr-8 pl-3 text-sm font-medium outline-none"
                                                    aria-label="Select model"
                                                >
                                                    {modelOptions.map(
                                                        (model) => (
                                                            <option
                                                                key={
                                                                    model.value
                                                                }
                                                                value={
                                                                    model.value
                                                                }
                                                            >
                                                                {model.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                <ChevronDown className="pointer-events-none absolute top-1/2 right-2 size-4 -translate-y-1/2 text-muted-foreground" />
                                            </div>
                                            <textarea
                                                name="message"
                                                rows={2}
                                                value={draft}
                                                onChange={(event) =>
                                                    setDraft(event.target.value)
                                                }
                                                onKeyDown={(event) => {
                                                    if (
                                                        event.key !== 'Enter' ||
                                                        event.shiftKey ||
                                                        event.nativeEvent
                                                            .isComposing
                                                    ) {
                                                        return;
                                                    }

                                                    event.preventDefault();

                                                    if (
                                                        draft.trim().length ===
                                                        0
                                                    ) {
                                                        return;
                                                    }

                                                    event.currentTarget
                                                        .closest('form')
                                                        ?.querySelector<HTMLButtonElement>(
                                                            '[data-chat-submit]',
                                                        )
                                                        ?.click();
                                                }}
                                                placeholder="Message Gemini..."
                                                className="min-h-16 w-full resize-none border-0 bg-transparent px-3 py-2 text-sm outline-none placeholder:text-muted-foreground"
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            size="icon"
                                            className="mt-1"
                                            data-chat-submit
                                            disabled={
                                                processing ||
                                                draft.trim().length === 0
                                            }
                                        >
                                            {processing ? (
                                                <LoaderCircle className="animate-spin" />
                                            ) : (
                                                <SendHorizontal />
                                            )}
                                        </Button>
                                    </div>
                                    <InputError message={errors.message} />
                                    <InputError message={errors.credits} />

                                    {processing && (
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <LoaderCircle className="size-4 animate-spin" />
                                            Gemini is thinking...
                                        </div>
                                    )}
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

            {activeConversation && regenerateMessage && (
                <Dialog
                    open={Boolean(regenerateMessage)}
                    onOpenChange={(open) => {
                        if (!open) {
                            setRegenerateMessage(null);
                        }
                    }}
                >
                    <DialogContent>
                        <Form
                            action={`/chat/${activeConversation.id}/messages/${regenerateMessage.id}/regenerate`}
                            method="post"
                            className="space-y-6"
                            onSuccess={() => setRegenerateMessage(null)}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Regenerate response
                                        </DialogTitle>
                                    </DialogHeader>

                                    <input
                                        type="hidden"
                                        name="model"
                                        value={regenerateModel}
                                    />

                                    <div className="grid gap-2">
                                        {modelOptions.map((model) => {
                                            const badge = modelBadge(
                                                model.value,
                                            );
                                            const BadgeIcon = badge.icon;

                                            return (
                                                <button
                                                    key={model.value}
                                                    type="button"
                                                    className={cn(
                                                        'rounded-md border px-3 py-2 text-left transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                                        regenerateModel ===
                                                            model.value
                                                            ? 'border-primary bg-primary text-primary-foreground'
                                                            : 'bg-background hover:bg-accent',
                                                    )}
                                                    onClick={() =>
                                                        setRegenerateModel(
                                                            model.value,
                                                        )
                                                    }
                                                >
                                                    <span className="flex items-center justify-between gap-2">
                                                        <span className="truncate text-sm font-medium">
                                                            {model.label}
                                                        </span>
                                                        <span className="inline-flex items-center gap-1 text-xs opacity-80">
                                                            <BadgeIcon className="size-3" />
                                                            {badge.label}
                                                        </span>
                                                    </span>
                                                    <span className="mt-1 block truncate text-xs opacity-80">
                                                        {model.description}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                        <InputError message={errors.model} />
                                    </div>

                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={() =>
                                                setRegenerateMessage(null)
                                            }
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing && (
                                                <LoaderCircle className="animate-spin" />
                                            )}
                                            Regenerate
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
