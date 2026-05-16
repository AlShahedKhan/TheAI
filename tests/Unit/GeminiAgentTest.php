<?php

namespace Tests\Unit;

use App\Ai\Agents\GeminiAgent;
use App\Models\User;
use Tests\TestCase;

class GeminiAgentTest extends TestCase
{
    public function test_gemini_agent_does_not_register_conflicting_tools(): void
    {
        $agent = new GeminiAgent(new User);

        $this->assertSame([], $agent->tools());
    }

    public function test_gemini_agent_instructions_cover_current_information(): void
    {
        $agent = new GeminiAgent(new User);

        $this->assertStringContainsString('live verification', (string) $agent->instructions());
        $this->assertStringContainsString('latest', (string) $agent->instructions());
    }
}
