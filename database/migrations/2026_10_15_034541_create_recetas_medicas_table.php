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
        Schema::create('recetas_medicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atencion_id')->constrained('atenciones_clinicas')->cascadeOnDelete();
            $table->string('medicamento');
            $table->string('dosis');
            $table->text('indicaciones');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recetas_medicas');
    }
};
