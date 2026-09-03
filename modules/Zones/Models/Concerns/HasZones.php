<?php

namespace Modules\Zones\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Modules\Zones\Models\Zone;

/**
 * Put zones on a model.
 *
 * `use HasZones;` and the model can be attached to any number of zones, asked
 * which zones it is in, and filtered by zone in a list — with no migration,
 * because {@see \Modules\Zones\Models\Zone} keeps the pairs in one polymorphic
 * table.
 *
 * **Roles are what keep the one table honest.** A provider's zones are where it
 * will travel (`coverage`); an organization's are where it trades
 * (`operating`); a shipment's is where it is going (`destination`). Same pair
 * of ids, different questions, so a model declares which role it means and the
 * relation never mixes them.
 */
trait HasZones
{
    /**
     * What this model's zones mean. Override to change it.
     *
     * `coverage` is the default because "the areas this thing serves" is what
     * almost everything wants.
     */
    public function zoneRole(): string
    {
        return 'coverage';
    }

    /** Whether this model holds at most one zone. */
    public function hasSingleZone(): bool
    {
        return false;
    }

    public function zones(): MorphToMany
    {
        return $this->morphToMany(Zone::class, 'zonable', 'zonables', 'zonable_id', 'zone_id')
            ->withPivot('role')
            ->wherePivot('role', $this->zoneRole())
            ->withTimestamps();
    }

    /**
     * The one zone, for models that carry a single one.
     *
     * Not named `zone()`: ServiceHub providers already have a free-text `zone`
     * column, and a method and an attribute answering to one name is a thing
     * that reads fine and resolves two different ways.
     */
    public function primaryZone(): ?Zone
    {
        return $this->zones->first();
    }

    /**
     * Replace this model's zones with the given ids.
     *
     * `syncWithPivotValues` rather than `sync`: without the role on the pivot,
     * syncing a model's `coverage` zones would silently detach the `operating`
     * ones it also holds.
     *
     * @param  array<int,string>  $zoneIds
     */
    public function syncZones(array $zoneIds): void
    {
        $ids = array_values(array_filter(array_unique($zoneIds)));

        if ($this->hasSingleZone()) {
            $ids = array_slice($ids, 0, 1);
        }

        $this->zones()->syncWithPivotValues($ids, ['role' => $this->zoneRole()]);
    }

    /** Records attached to any of these zones. */
    public function scopeInZones(Builder $query, array $zoneIds): Builder
    {
        $ids = array_values(array_filter($zoneIds));

        if (! $ids) {
            return $query;
        }

        return $query->whereHas('zones', fn ($q) => $q->whereIn('zones.id', $ids));
    }

    /**
     * Records whose zones contain a point.
     *
     * Resolves the point to a zone once and filters on it, rather than testing
     * every record's polygons: the point does not move between rows.
     */
    public function scopeCoveringPoint(Builder $query, ?string $organizationId, float $lat, float $lng): Builder
    {
        $zone = Zone::locate($organizationId, $lat, $lng);

        // No zone covers the point, so nothing can serve it. Returning the
        // unfiltered list here would claim every provider covers a place none
        // of them do.
        return $zone ? $query->inZones([$zone->id]) : $query->whereRaw('1 = 0');
    }
}
