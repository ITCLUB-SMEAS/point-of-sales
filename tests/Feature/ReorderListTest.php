<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use App\Services\ReorderList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReorderListTest extends TestCase
{
    use RefreshDatabase;

    public function test_reorder_list_contains_only_active_tracked_low_stock_products(): void
    {
        $paper = Product::factory()->create([
            'name' => 'Kertas A4',
            'sku' => 'A4',
            'unit' => 'rim',
            'is_active' => true,
            'is_stock_tracked' => true,
            'stock_quantity' => 2,
            'minimum_stock' => 5,
        ]);

        Product::factory()->create([
            'name' => 'Pulpen Hitam',
            'is_active' => true,
            'is_stock_tracked' => true,
            'stock_quantity' => 10,
            'minimum_stock' => 5,
        ]);

        Product::factory()->create([
            'name' => 'Layanan Print',
            'is_active' => true,
            'is_stock_tracked' => false,
            'stock_quantity' => 0,
            'minimum_stock' => 5,
        ]);

        Product::factory()->create([
            'name' => 'Map Arsip Lama',
            'is_active' => false,
            'is_stock_tracked' => true,
            'stock_quantity' => 0,
            'minimum_stock' => 5,
        ]);

        $items = app(ReorderList::class)->items();

        $this->assertCount(1, $items);
        $this->assertSame($paper->id, $items[0]['id']);
        $this->assertSame('Kertas A4', $items[0]['name']);
        $this->assertSame(3, $items[0]['shortage']);
        $this->assertSame(3, $items[0]['recommended_order_quantity']);
    }

    public function test_admin_can_view_reorder_list_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Product::factory()->create([
            'name' => 'Kertas A4',
            'sku' => 'A4',
            'stock_quantity' => 2,
            'minimum_stock' => 5,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get('/admin/reorder-list');

        $response
            ->assertOk()
            ->assertSeeText('Stok Menipis')
            ->assertSeeText('Kertas A4')
            ->assertSeeText('Export CSV');
    }

    public function test_admin_can_export_reorder_list_csv(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Product::factory()->create([
            'name' => 'Kertas A4',
            'sku' => 'A4',
            'unit' => 'rim',
            'stock_quantity' => 2,
            'minimum_stock' => 5,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get('/admin/reorder-list/export/csv');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('"Daftar Belanja Stok"', $content);
        $this->assertStringContainsString('"Kertas A4",A4,2,5,3,rim', $content);
    }

    public function test_admin_dashboard_shows_low_stock_warning_items(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Product::factory()->create([
            'name' => 'Kertas A4',
            'sku' => 'A4',
            'stock_quantity' => 2,
            'minimum_stock' => 5,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get('/admin');

        $response
            ->assertOk()
            ->assertSeeText('Stok Menipis')
            ->assertSeeText('Kertas A4');
    }
}
