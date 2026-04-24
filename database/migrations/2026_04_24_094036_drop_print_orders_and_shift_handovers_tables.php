<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('print_orders');
        Schema::dropIfExists('shift_handovers');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
