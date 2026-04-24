<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->nullable()->unique();
            $table->string('name');
            $table->string('type')->index();
            $table->string('unit')->default('item');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('cost')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_stock_tracked')->default(false);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->timestamps();
        });

        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->unsignedBigInteger('opening_cash');
            $table->unsignedBigInteger('expected_closing_cash')->nullable();
            $table->unsignedBigInteger('closing_cash')->nullable();
            $table->integer('cash_variance')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('cashier_shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->index();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('paid_total');
            $table->unsignedBigInteger('change_total')->default(0);
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('subtotal');
            $table->string('source_note')->nullable();
            $table->boolean('is_stock_tracked')->default(false);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_transaction_id')->constrained()->cascadeOnDelete();
            $table->string('method')->index();
            $table->unsignedBigInteger('amount');
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('approvable');
            $table->string('action')->index();
            $table->string('status')->index();
            $table->text('reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('auditable');
            $table->string('event')->index();
            $table->json('properties')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->integer('quantity');
            $table->integer('stock_after');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_transaction_items');
        Schema::dropIfExists('sale_transactions');
        Schema::dropIfExists('cashier_shifts');
        Schema::dropIfExists('products');
    }
};
