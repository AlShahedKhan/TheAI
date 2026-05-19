<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'created_by', 'type', 'credits', 'amount', 'currency', 'meta'])]
class CreditTransaction extends Model
{
    use HasFactory;

    public const TYPE_ADMIN_RECHARGE = 'admin_recharge';

    public const TYPE_USER_PURCHASE = 'user_purchase';

    public const TYPE_CHAT_USAGE = 'chat_usage';

    public const TYPE_VIDEO_USAGE = 'video_usage';

    public const CREDITS_PER_USD = 150;

    public const BDT_PER_CREDIT = 1;

    public const CHAT_MESSAGE_COST = 1;

    public const VIDEO_GENERATION_COST = 100;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
