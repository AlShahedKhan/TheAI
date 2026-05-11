import { Form, Head } from '@inertiajs/react';
import { Clapperboard, LoaderCircle, RefreshCw, Send } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type ModelOption = {
    value: string;
    label: string;
    description: string;
};

type VideoGeneration = {
    id: number;
    model: string;
    prompt: string;
    aspect_ratio: string;
    resolution: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    video_url: string | null;
    error: string | null;
    created_at: string;
};

type Props = {
    generations: VideoGeneration[];
    modelOptions: ModelOption[];
    aspectRatios: string[];
    resolutions: string[];
    defaultModel: string;
};

const statusClasses: Record<VideoGeneration['status'], string> = {
    pending: 'border-amber-200 bg-amber-50 text-amber-800',
    processing: 'border-blue-200 bg-blue-50 text-blue-800',
    completed: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    failed: 'border-red-200 bg-red-50 text-red-800',
};

export default function VideosIndex({
    generations,
    modelOptions,
    aspectRatios,
    resolutions,
    defaultModel,
}: Props) {
    return (
        <>
            <Head title="Video" />

            <div className="flex h-[calc(100vh-5rem)] flex-col overflow-hidden">
                <div className="flex items-center justify-between gap-3 border-b px-4 py-3">
                    <div>
                        <h1 className="text-lg font-semibold">
                            Veo Video Generation
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Create short videos from text prompts.
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <a href="/videos">
                            <RefreshCw />
                            Refresh
                        </a>
                    </Button>
                </div>

                <div className="grid min-h-0 flex-1 grid-cols-1 overflow-hidden lg:grid-cols-[24rem_1fr]">
                    <aside className="border-b p-4 lg:border-r lg:border-b-0">
                        <Form
                            action="/videos"
                            method="post"
                            resetOnSuccess={['prompt']}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="model">Model</Label>
                                        <select
                                            id="model"
                                            name="model"
                                            defaultValue={defaultModel}
                                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            {modelOptions.map((model) => (
                                                <option
                                                    key={model.value}
                                                    value={model.value}
                                                >
                                                    {model.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.model} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="aspect_ratio">
                                            Aspect ratio
                                        </Label>
                                        <select
                                            id="aspect_ratio"
                                            name="aspect_ratio"
                                            defaultValue="16:9"
                                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            {aspectRatios.map((ratio) => (
                                                <option
                                                    key={ratio}
                                                    value={ratio}
                                                >
                                                    {ratio}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.aspect_ratio}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="resolution">
                                            Resolution
                                        </Label>
                                        <select
                                            id="resolution"
                                            name="resolution"
                                            defaultValue="720p"
                                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        >
                                            {resolutions.map((resolution) => (
                                                <option
                                                    key={resolution}
                                                    value={resolution}
                                                >
                                                    {resolution}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.resolution}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="prompt">Prompt</Label>
                                        <textarea
                                            id="prompt"
                                            name="prompt"
                                            rows={8}
                                            className="min-h-40 w-full resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            placeholder="A cinematic shot of..."
                                            required
                                        />
                                        <InputError message={errors.prompt} />
                                    </div>

                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        {processing ? (
                                            <LoaderCircle className="animate-spin" />
                                        ) : (
                                            <Send />
                                        )}
                                        Generate Video
                                    </Button>
                                </>
                            )}
                        </Form>
                    </aside>

                    <main className="min-h-0 overflow-y-auto p-4">
                        {generations.length === 0 ? (
                            <div className="flex h-full min-h-80 flex-col items-center justify-center rounded-lg border border-dashed text-center text-sm text-muted-foreground">
                                <Clapperboard className="mb-3 h-8 w-8" />
                                Generated videos will appear here.
                            </div>
                        ) : (
                            <div className="grid gap-4 xl:grid-cols-2">
                                {generations.map((generation) => (
                                    <article
                                        key={generation.id}
                                        className="overflow-hidden rounded-lg border bg-card"
                                    >
                                        <div className="aspect-video bg-muted">
                                            {generation.video_url ? (
                                                <video
                                                    src={generation.video_url}
                                                    controls
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                                                    {generation.status ===
                                                    'failed'
                                                        ? 'Generation failed'
                                                        : 'Waiting for video'}
                                                </div>
                                            )}
                                        </div>
                                        <div className="space-y-3 p-4">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span
                                                    className={`rounded-md border px-2 py-1 text-xs font-medium ${statusClasses[generation.status]}`}
                                                >
                                                    {generation.status}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {generation.model}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {generation.aspect_ratio}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {generation.resolution}
                                                </span>
                                            </div>
                                            <p className="line-clamp-4 text-sm leading-6">
                                                {generation.prompt}
                                            </p>
                                            {generation.error && (
                                                <p className="text-sm text-destructive">
                                                    {generation.error}
                                                </p>
                                            )}
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}
                    </main>
                </div>
            </div>
        </>
    );
}

VideosIndex.layout = () => ({
    breadcrumbs: [
        {
            title: 'Video',
            href: '/videos',
        },
    ],
});
