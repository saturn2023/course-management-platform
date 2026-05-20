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
        Schema::create('xero_connections', function (Blueprint $table) {
           $table->id();

$table->string('tenant_id')->nullable();
$table->string('tenant_name')->nullable();

$table->longText('access_token')->nullable();
$table->longText('refresh_token')->nullable();

$table->timestamp('expires_at')->nullable();

$table->string('branding_theme_id')->nullable();
$table->string('branding_theme_name')->nullable();

$table->boolean('is_active')->default(true);

$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xero_connections');
    }
};
