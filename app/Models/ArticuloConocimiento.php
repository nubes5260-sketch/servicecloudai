<?php

namespace App\Models;

use App\Enums\EstadoArticulo;
use App\Enums\VisibilidadArticulo;
use App\Models\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** organizacion_id NULL = artículo público de plataforma. */
class ArticuloConocimiento extends Model
{
    use PerteneceAOrganizacion;

    protected static bool $incluyeGlobales = true;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = 'actualizado_en';

    protected $table = 'articulos_conocimiento';

    /** La categoría se muestra en todas las vistas de la base de conocimiento. */
    protected $with = ['categoriaCatalogo'];

    protected $fillable = [
        'organizacion_id', 'codigo', 'categoria_id', 'categoria', 'titulo', 'resumen', 'contenido', 'autor_id',
        'visibilidad', 'estado', 'vistas', 'version',
    ];

    protected function casts(): array
    {
        return [
            'visibilidad' => VisibilidadArticulo::class,
            'estado' => EstadoArticulo::class,
            'vistas' => 'integer',
            'version' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'codigo';
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autor_id');
    }

    public function etiquetas(): BelongsToMany
    {
        return $this->belongsToMany(Etiqueta::class, 'articulos_etiquetas', 'articulo_id', 'etiqueta_id');
    }

    /** Categoría del catálogo categorias_conocimiento. */
    public function categoriaCatalogo(): BelongsTo
    {
        return $this->belongsTo(CategoriaConocimiento::class, 'categoria_id');
    }

    /**
     * Nombre de la categoría. Leer devuelve el nombre del catálogo; asignar un nombre
     * ("Redes") guarda su categoria_id. Así las vistas siguen usando ->categoria.
     */
    protected function categoria(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->categoriaCatalogo?->nombre,
            set: fn (?string $nombre) => ['categoria_id' => $nombre ? CategoriaConocimiento::idDe($nombre) : null],
        );
    }

    /** Filtra por nombre de categoría. */
    public function scopeDeCategoria(Builder $consulta, string $nombre): Builder
    {
        return $consulta->whereRelation('categoriaCatalogo', 'nombre', $nombre);
    }
}
