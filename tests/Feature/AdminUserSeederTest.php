<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_seeder_creates_admin_with_personal_team(): void
    {
        config([
            'admin.user.name' => 'Site Admin',
            'admin.user.email' => 'admin@example.com',
            'admin.user.workos_id' => 'workos-admin-id',
        ]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $team = $admin->personalTeam();

        $this->assertSame('Site Admin', $admin->name);
        $this->assertSame('workos-admin-id', $admin->workos_id);
        $this->assertTrue($admin->is_admin);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertNotNull($team);
        $this->assertEquals($team->id, $admin->current_team_id);
        $this->assertSame(TeamRole::Owner, $admin->teamRole($team));
    }

    public function test_admin_user_seeder_is_idempotent(): void
    {
        config([
            'admin.user.email' => 'admin@example.com',
            'admin.user.workos_id' => 'workos-admin-id',
        ]);

        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
    }
}
