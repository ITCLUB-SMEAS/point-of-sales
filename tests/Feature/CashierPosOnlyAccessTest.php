<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Responses\LoginResponse;
use App\Models\CashierShift;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierPosOnlyAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_is_redirected_from_admin_panel_to_pos(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);

        $response = $this
            ->actingAs($cashier)
            ->get('/admin');

        $response->assertRedirect(route('pos.index'));
    }

    public function test_cashier_pos_hides_admin_link_and_cash_movement_form(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        CashierShift::openFor($cashier, 10000);

        $response = $this
            ->actingAs($cashier)
            ->get(route('pos.index'));

        $response
            ->assertOk()
            ->assertDontSeeText('Admin')
            ->assertDontSeeText('Kas Keluar / Setoran')
            ->assertDontSee(route('pos.cash-movements.store'), false);
    }

    public function test_admin_still_sees_admin_link_and_cash_movement_form_on_pos(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        CashierShift::openFor($admin, 10000);

        $response = $this
            ->actingAs($admin)
            ->get(route('pos.index'));

        $response
            ->assertOk()
            ->assertSeeText('Admin')
            ->assertSeeText('Kas Keluar / Setoran')
            ->assertSee(route('pos.cash-movements.store'), false);
    }

    public function test_login_response_redirects_cashier_to_pos_and_admin_to_panel(): void
    {
        $this->assertInstanceOf(LoginResponse::class, app(LoginResponseContract::class));

        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($cashier);
        $cashierResponse = app(LoginResponseContract::class)->toResponse(request());
        $this->assertSame(route('pos.index'), $cashierResponse->getTargetUrl());

        $this->actingAs($admin);
        $adminResponse = app(LoginResponseContract::class)->toResponse(request());
        $this->assertStringEndsWith('/admin', $adminResponse->getTargetUrl());
    }
}
