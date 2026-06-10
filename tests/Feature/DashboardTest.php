<?php

namespace Tests\Feature;

use App\Models\CreditPurchaseRequest;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_admin_dashboard_metrics_show_on_team_dashboard()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        CreditTransaction::create([
            'created_by' => $admin->id,
            'type' => CreditTransaction::TYPE_ADMIN_RECHARGE,
            'credits' => 150,
            'amount' => 1,
            'currency' => 'USD',
            'meta' => ['mode' => 'test'],
        ]);
        CreditTransaction::create([
            'user_id' => $user->id,
            'created_by' => $admin->id,
            'type' => CreditTransaction::TYPE_USER_PURCHASE,
            'credits' => 100,
            'amount' => 100,
            'currency' => 'BDT',
            'meta' => ['mode' => 'test'],
        ]);
        CreditPurchaseRequest::create([
            'user_id' => $user->id,
            'credits' => 100,
            'amount_bdt' => 100,
            'payment_method' => 'bkash',
            'transaction_id' => 'PENDING-1',
            'sender_number' => '01700000000',
            'status' => CreditPurchaseRequest::STATUS_PENDING,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('dashboard', $admin->currentTeam->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('dashboard')
                ->where('adminSummary.pending_payments', 1)
                ->where('adminSummary.credits_sold', 100)
                ->where('adminSummary.site_available', 50)
                ->where('adminSummary.revenue_bdt', 100)
            );
    }
}
