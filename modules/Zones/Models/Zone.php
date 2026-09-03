<?php

namespace Modules\Zones\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * An area somebody drew on a map.
 *
 * The ring lives in `coordinates` as `[[lat, lng], ...]`; the bounding box and
 * centroid beside it are derived, recomputed on every save by
 * {@see refreshGeometry()} so they cannot drift from the ring they describe.
 */
class Zone extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'code', 'name', 'description', 'colour',
        'coordinates', 'min_lat', 'max_lat', 'min_lng', 'max_lng',
        'centre_lat', 'centre_lng', 'active',
    ];

    protected $casts = [
        'coordinates' => 'array',
        'active' => 'boolean',
        'min_lat' => 'float', 'max_lat' => 'float',
        'min_lng' => 'float', 'max_lng' => 'float',
        'centre_lat' => 'float', 'centre_lng' => 'float',
    ];

    /**
     * Keep the derived geometry in step with the ring.
     *
     * On save rather than on read: the box is what the database filters on, so
     * it has to be a column, and a column that is only refreshed when someone
     * remembers to call a method is a column that will be wrong.
     */
    protected static function booted(): void
    {
        static::saving(function (Zone $zone) {
            $zone->refreshGeometry();
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Everything of one kind that sits in this zone.
     *
     * @param  class-string<Model>  $model
     */
    public function zonables(string $model, ?string $role = null): MorphToMany
    {
        $relation = $this->morphedByMany($model, 'zonable', 'zonables', 'zone_id', 'zonable_id')
            ->withPivot('role')
            ->withTimestamps();

        return $role ? $relation->wherePivot('role', $role) : $relation;
    }

    /** The ring as `[[lat, lng], ...]`, empty when nothing has been drawn. */
    public function ring(): array
    {
        return array_values(array_filter(
            (array) ($this->coordinates ?? []),
            fn ($point) => is_array($point) && count($point) >= 2,
        ));
    }

    public function isDrawn(): bool
    {
        return count($this->ring()) >= 3;
    }

    /**
     * Whether a point falls inside this zone.
     *
     * The box is checked first because it is a handful of comparisons and
     * rejects almost everything; the ray cast below only runs for the few
     * points that could plausibly be inside.
     */
    public function contains(float $lat, float $lng): bool
    {
        if (! $this->isDrawn()) {
            return false;
        }

        if ($this->min_lat !== null && (
            $lat < $this->min_lat || $lat > $this->max_lat
            || $lng < $this->min_lng || $lng > $this->max_lng
        )) {
            return false;
        }

        return self::ringContains($this->ring(), $lat, $lng);
    }

    /**
     * Ray casting: count how many edges a ray from the point crosses.
     *
     * Odd means inside. The `($ay > $lat) !== ($by > $lat)` test is what makes
     * a vertex exactly level with the ray count once rather than twice — the
     * detail that, left out, makes points due east of a corner come out wrong.
     *
     * @param  array<int,array{0:float,1:float}>  $ring
     */
    public static function ringContains(array $ring, float $lat, float $lng): bool
    {
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $ay = (float) $ring[$i][0];
            $ax = (float) $ring[$i][1];
            $by = (float) $ring[$j][0];
            $bx = (float) $ring[$j][1];

            if (($ay > $lat) !== ($by > $lat)
                && $lng < ($bx - $ax) * ($lat - $ay) / (($by - $ay) ?: 1e-12) + $ax) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * The zone containing a point, or null.
     *
     * The bounding boxes do the work in SQL; at most a few candidates come back
     * for the ring walk. Newest first, so that redrawing an area to correct it
     * wins over the older zone it overlaps rather than depending on insert
     * order.
     */
    public static function locate(?string $organizationId, float $lat, float $lng): ?self
    {
        return static::query()
            ->where('organization_id', $organizationId)
            ->active()
            ->where('min_lat', '<=', $lat)->where('max_lat', '>=', $lat)
            ->where('min_lng', '<=', $lng)->where('max_lng', '>=', $lng)
            ->orderByDesc('created_at')
            ->get()
            ->first(fn (self $zone) => $zone->contains($lat, $lng));
    }

    /** Recompute the box and centroid from the ring. */
    public function refreshGeometry(): void
    {
        $ring = $this->ring();

        if (! $ring) {
            $this->min_lat = $this->max_lat = $this->min_lng = $this->max_lng = null;
            $this->centre_lat = $this->centre_lng = null;

            return;
        }

        $lats = array_map(fn ($p) => (float) $p[0], $ring);
        $lngs = array_map(fn ($p) => (float) $p[1], $ring);

        $this->min_lat = min($lats);
        $this->max_lat = max($lats);
        $this->min_lng = min($lngs);
        $this->max_lng = max($lngs);

        // The box centre, not the polygon's true centroid: this only ever
        // positions a map pin, and an area-weighted centroid is arithmetic
        // nobody here would be able to check against the picture.
        $this->centre_lat = ($this->min_lat + $this->max_lat) / 2;
        $this->centre_lng = ($this->min_lng + $this->max_lng) / 2;
    }

    /**
     * Rough area in square kilometres, for the list.
     *
     * The shoelace formula on degrees, with the longitude axis scaled by
     * cos(latitude) so the answer is not wildly wrong away from the equator.
     * It is an estimate for sorting and sanity-checking a drawing, not a
     * survey figure.
     */
    public function approximateAreaKm2(): float
    {
        $ring = $this->ring();

        if (count($ring) < 3) {
            return 0.0;
        }

        $kmPerDegreeLat = 110.574;
        $kmPerDegreeLng = 111.320 * cos(deg2rad((float) ($this->centre_lat ?? 0)));

        $sum = 0.0;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $sum += ((float) $ring[$j][1] * $kmPerDegreeLng) * ((float) $ring[$i][0] * $kmPerDegreeLat)
                - ((float) $ring[$i][1] * $kmPerDegreeLng) * ((float) $ring[$j][0] * $kmPerDegreeLat);
        }

        return round(abs($sum) / 2, 2);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'colour' => $this->colour,
            'coordinates' => $this->ring(),
            'centre' => $this->centre_lat === null ? null : ['lat' => $this->centre_lat, 'lng' => $this->centre_lng],
            'bounds' => $this->min_lat === null ? null : [
                'min_lat' => $this->min_lat, 'max_lat' => $this->max_lat,
                'min_lng' => $this->min_lng, 'max_lng' => $this->max_lng,
            ],
            'area_km2' => $this->approximateAreaKm2(),
            'active' => $this->active,
        ];
    }
}
