<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relation_nullability_owners', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('relation_nullability_models', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('required_owner_id');
            $table->unsignedBigInteger('nullable_owner_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relation_nullability_models');
        Schema::dropIfExists('relation_nullability_owners');
    }
};
