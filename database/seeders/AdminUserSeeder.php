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

        $workosId = config('admin.user.workos_id');
        $user = User::where('email', $email)->first();

        if (! $user && ! $workosId && ! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping admin user seed because ADMIN_USER_WORKOS_ID is required when creating an admin outside local/testing.');

            return;
        }

        $attributes = [
            'name' => config('admin.user.name', 'Admin User'),
            'email_verified_at' => now(),
            'avatar' => $user?->avatar ?? '',
            'is_admin' => true,
        ];

        if ($workosId || ! $user) {
            $attributes['workos_id'] = $workosId ?: 'seeded-admin-'.Str::lower(Str::slug($email));
        }

        $user = User::updateOrCreate(['email' => $email], $attributes);

        if (! $user->personalTeam()) {
            app(CreateTeam::class)->handle($user, $user->name."'s Team", isPersonal: true);
        }

        $this->command?->info("Admin user ready: {$user->email}");
    }
}
