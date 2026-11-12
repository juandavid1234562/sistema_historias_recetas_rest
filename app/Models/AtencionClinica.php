<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AtencionClinica extends Model
{
    use HasFactory;

    /**
     * Nombre de la tabla asociada al modelo en la base de datos.
     *
     * @var string
     */
    protected $table = 'atenciones_clinicas';

    /**
     * Atributos asignables de forma masiva (Mass Assignment Protection).
     * Cumpliendo con las directrices de seguridad de Eloquent ORM.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cita_id',
        'diagnostico',
        'observaciones',
        'fecha_atencion',
    ];

    /**
     * Atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_atencion' => 'datetime',
        'cita_id' => 'integer',
    ];

    /**
     * Relación uno a muchos: Una atención clínica puede tener múltiples recetas médicas.
     */
    public function recetas(): HasMany
    {
        return $this->hasMany(RecetaMedica::class, 'atencion_id');
    }
}
