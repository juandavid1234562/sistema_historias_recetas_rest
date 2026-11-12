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
        Schema::create('atenciones_clinicas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cita_id')->index()->comment('ID de la cita validada desde el servicio SOAP');
            $table->text('diagnostico');
            $table->text('observaciones')->nullable();
            $table->dateTime('fecha_atencion');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atenciones_clinicas');
    }
};
