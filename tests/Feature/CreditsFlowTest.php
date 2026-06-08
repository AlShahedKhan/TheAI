<?php

namespace Tests\Feature;

use App\Models\CreditPurchaseRequest;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CreditsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_pending_manual_payment_request(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('credits.purchase'), [
                'credits' => 100,
                'payment_method' => 'bkash',
                'transaction_id' => 'TXN-100',
                'sender_number' => '01700000000',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('credit_purchase_requests', [
            'user_id' => $user->id,
            'credits' => 100,
            'amount_bdt' => 100,
            'payment_method' => 'bkash',
            'transaction_id' => 'TXN-100',
            'status' => CreditPurchaseRequest::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('credit_transactions', [
            'user_id' => $user->id,
            'type' => CreditTransaction::TYPE_USER_PURCHASE,
        ]);
    }

    public function test_duplicate_transaction_id_is_rejected(): void
    {
        $user = User::factory()->create();

        CreditPurchaseRequest::create([
            'user_id' => $user->id,
            'credits' => 100,
            'amount_bdt' => 100,
            'payment_method' => 'bkash',
            'transaction_id' => 'DUPLICATE',
            'sender_number' => '01700000000',
            'status' => CreditPurchaseRequest::STATUS_PENDING,
        ]);

        $this
            ->actingAs($user)
            ->post(route('credits.purchase'), [
                'credits' => 100,
                'payment_method' => 'bkash',
                'transaction_id' => 'DUPLICATE',
                'sender_number' => '01700000001',
            ])
            ->assertSessionHasErrors('transaction_id');
    }

    public function test_admin_can_approve_payment_and_add_credits(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $purchase = CreditPurchaseRequest::create([
            'user_id' => $user->id,
            'credits' => 100,
            'amount_bdt' => 100,
            'payment_method' => 'nagad',
            'transaction_id' => 'APPROVE-1',
            'sender_number' => '01800000000',
            'status' => CreditPurchaseRequest::STATUS_PENDING,
        ]);

        CreditTransaction::create([
            'created_by' => $admin->id,
            'type' => CreditTransaction::TYPE_ADMIN_RECHARGE,
            'credits' => 150,
            'amount' => 1,
            'currency' => 'USD',
            'meta' => ['mode' => 'test'],
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.payments.approve', $purchase))
            ->assertRedirect();

        $this->assertDatabaseHas('credit_purchase_requests', [
            'id' => $purchase->id,
            'status' => CreditPurchaseRequest::STATUS_APPROVED,
            'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'created_by' => $admin->id,
            'type' => CreditTransaction::TYPE_USER_PURCHASE,
            'credits' => 100,
            'amount' => 100,
            'currency' => 'BDT',
        ]);
    }

    public function test_admin_can_reject_payment_without_adding_credits(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $purchase = CreditPurchaseRequest::create([
            'user_id' => $user->id,
            'credits' => 100,
            'amount_bdt' => 100,
            'payment_method' => 'bkash',
            'transaction_id' => 'REJECT-1',
            'sender_number' => '01700000000',
            'status' => CreditPurchaseRequest::STATUS_PENDING,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.payments.reject', $purchase), [
                'rejection_reason' => 'Payment not found',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('credit_purchase_requests', [
            'id' => $purchase->id,
            'status' => CreditPurchaseRequest::STATUS_REJECTED,
            'rejection_reason' => 'Payment not found',
        ]);
        $this->assertDatabaseMissing('credit_transactions', [
            'user_id' => $user->id,
            'type' => CreditTransaction::TYPE_USER_PURCHASE,
        ]);
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('admin.index'))
            ->assertForbidden();
    }

    public function test_admin_dashboard_shows_business_metrics(): void
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

        $this
            ->actingAs($admin)
            ->get(route('admin.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/index')
                ->where('metrics.credits_sold', 100)
                ->where('metrics.site_available', 50)
                ->where('metrics.revenue_bdt', 100)
            );
    }

    public function test_credit_settings_update_affects_future_purchase_amounts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this
            ->actingAs($admin)
            ->patch(route('admin.credits.settings.update'), [
                'bdt_per_credit' => 2,
                'chat_message_cost' => 1,
                'video_generation_cost' => 100,
                'daily_spend_limit' => 1000,
                'daily_video_limit' => 5,
                'payment_number' => '01711111111',
                'packages' => '100,500,1000',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('credit_settings', [
            'key' => 'bdt_per_credit',
        ]);

        $this
            ->actingAs($user)
            ->post(route('credits.purchase'), [
                'credits' => 100,
                'payment_method' => 'bkash',
                'transaction_id' => 'SETTINGS-1',
                'sender_number' => '01700000000',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('credit_purchase_requests', [
            'transaction_id' => 'SETTINGS-1',
            'amount_bdt' => 200,
        ]);
    }

    public function test_admin_can_suspend_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this
            ->actingAs($admin)
            ->patch(route('admin.users.suspend', $user), [
                'is_suspended' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_suspended' => true,
        ]);
    }
}
