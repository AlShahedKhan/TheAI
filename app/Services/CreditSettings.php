<?php

namespace App\Services;

use App\Models\CreditSetting;

class CreditSettings
{
    public const DEFAULT_BDT_PER_CREDIT = 1;

    public const DEFAULT_CHAT_MESSAGE_COST = 1;

    public const DEFAULT_VIDEO_GENERATION_COST = 100;

    public const DEFAULT_DAILY_SPEND_LIMIT = 1000;

    public const DEFAULT_DAILY_VIDEO_LIMIT = 5;

    public const DEFAULT_PAYMENT_NUMBER = '01XXXXXXXXX';

    public function all(): array
    {
        return [
            'bdt_per_credit' => $this->integer('bdt_per_credit', self::DEFAULT_BDT_PER_CREDIT),
            'chat_message_cost' => $this->integer('chat_message_cost', self::DEFAULT_CHAT_MESSAGE_COST),
            'video_generation_cost' => $this->integer('video_generation_cost', self::DEFAULT_VIDEO_GENERATION_COST),
            'daily_spend_limit' => $this->integer('daily_spend_limit', self::DEFAULT_DAILY_SPEND_LIMIT),
            'daily_video_limit' => $this->integer('daily_video_limit', self::DEFAULT_DAILY_VIDEO_LIMIT),
            'payment_number' => $this->string('payment_number', self::DEFAULT_PAYMENT_NUMBER),
            'packages' => $this->packages(),
        ];
    }

    public function packages(): array
    {
        return collect($this->value('packages', [100, 500, 1000]))
            ->map(fn ($credits) => (int) $credits)
            ->filter(fn ($credits) => $credits > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function update(array $values): void
    {
        foreach ($values as $key => $value) {
            CreditSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }

    public function integer(string $key, int $default): int
    {
        return (int) $this->value($key, $default);
    }

    public function string(string $key, string $default): string
    {
        return (string) $this->value($key, $default);
    }

    private function value(string $key, mixed $default): mixed
    {
        $setting = CreditSetting::query()->where('key', $key)->first();

        return $setting?->value ?? $default;
    }
}
