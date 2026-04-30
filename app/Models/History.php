<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'conversation_id', 'user_id', 'agent', 'role', 'content', 'attachments', 'tool_calls', 'tool_results', 'usage', 'meta'])]
class History extends Model
{
    protected $table = 'agent_conversation_messages';

    protected $keyType = 'string';

    public $incrementing = false;
}
