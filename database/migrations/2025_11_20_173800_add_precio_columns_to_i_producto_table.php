<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('i_producto', function (Blueprint $table) {
            if (! Schema::hasColumn('i_producto', 'precio')) {
                $table->decimal('precio', 10, 2)->nullable()->after('stock_actual');
            }
            if (! Schema::hasColumn('i_producto', 'precio_costo')) {
                $table->decimal('precio_costo', 10, 2)->nullable()->after('precio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('i_producto', function (Blueprint $table) {
            if (Schema::hasColumn('i_producto', 'precio_costo')) {
                $table->dropColumn('precio_costo');
            }
            if (Schema::hasColumn('i_producto', 'precio')) {
                $table->dropColumn('precio');
            }
        });
    }
};
