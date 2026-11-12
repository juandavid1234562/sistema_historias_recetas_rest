<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecetaMedica extends Model
{
    use HasFactory;

    /**
     * Nombre de la tabla asociada al modelo en la base de datos.
     *
     * @var string
     */
    protected $table = 'recetas_medicas';

    /**
     * Atributos asignables de forma masiva (Mass Assignment Protection).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'atencion_id',
        'medicamento',
        'dosis',
        'indicaciones',
    ];

    /**
     * Atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'atencion_id' => 'integer',
    ];

    /**
     * Relación muchos a uno: Cada receta médica pertenece a una única atención clínica.
     */
    public function atencion(): BelongsTo
    {
        return $this->belongsTo(AtencionClinica::class, 'atencion_id');
    }
}
