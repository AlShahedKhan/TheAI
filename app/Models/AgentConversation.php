<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id', 'user_id', 'title'])]
class AgentConversation extends Model
{
    protected $table = 'agent_conversations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return HasMany<History, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(History::class, 'conversation_id');
    }
}
