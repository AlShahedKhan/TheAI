<?php

namespace Database\Seeders;

use App\Actions\Teams\CreateTeam;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the application admin user.
     */
    public function run(): void
    {
        $email = config('admin.user.email');

        if (! $email && app()->environment(['local', 'testing'])) {
            $email = 'admin@example.com';
        }

        if (! $email) {
            $this->command?->warn('Skipping admin user seed because ADMIN_USER_EMAIL is not configured.');

            return;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('admin.user.name', 'Admin User'),
                'workos_id' => config('admin.user.workos_id') ?: 'seeded-admin-'.Str::lower(Str::slug($email)),
                'email_verified_at' => now(),
                'avatar' => '',
                'is_admin' => true,
            ],
        );

        if (! $user->personalTeam()) {
            app(CreateTeam::class)->handle($user, $user->name."'s Team", isPersonal: true);
        }

        $this->command?->info("Admin user ready: {$user->email}");
    }
}
