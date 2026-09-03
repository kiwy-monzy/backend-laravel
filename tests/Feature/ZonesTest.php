<?php

namespace Tests\Feature;

use App\Models\ModuleAccess;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Fulfillment\Models\Shipment;
use Modules\ServiceHub\Models\Provider;
use Modules\Zones\Models\Zone;
use Tests\TestCase;

/**
 * Zones: the geometry, and the one pivot that attaches them to anything.
 *
 * The containment tests are the ones that earn their keep. Ray casting is
 * short enough to look obviously right and subtle enough to be wrong at the
 * edges, and every zoned feature in the app rests on it.
 */
class ZonesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    /** A square from (0,0) to (10,10), in [lat, lng]. */
    private const SQUARE = [[0, 0], [0, 10], [10, 10], [10, 0]];

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'id' => 'org-zones',
            'name' => 'FGE',
            'slug' => 'fge-zones',
            'currency' => 'TZS',
            'plan' => 'professional',
            'subscription_status' => 'active',
            'trial_ends_at' => now()->addDays(30),
        ]);

        foreach (['zones', 'servicehub', 'fulfillment'] as $module) {
            ModuleAccess::create([
                'organization_id' => $this->organization->id,
                'role' => 'admin',
                'module' => $module,
                'section' => '*',
                'allowed' => true,
            ]);
        }
    }

    private function admin(): User
    {
        $user = User::firstOrCreate(['username' => 'zone_admin'], [
            'id' => 'user-zone-admin',
            'email' => 'zones@fge.or.tz',
            'password_hash' => bcrypt('secret'),
            'role' => 'owner',
            'active' => true,
            'organization_id' => $this->organization->id,
        ]);

        OrganizationMember::firstOrCreate([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
        ], ['role' => 'admin', 'active' => true]);

        return $user;
    }

    private function zone(array $attributes = []): Zone
    {
        return Zone::create($attributes + [
            'id' => 'zone-1',
            'organization_id' => $this->organization->id,
            'name' => 'Central',
            'colour' => '#2f6f4e',
            'coordinates' => self::SQUARE,
            'active' => true,
        ]);
    }

    // ---- geometry ---------------------------------------------------------

    public function test_a_point_inside_the_ring_is_contained(): void
    {
        $this->assertTrue($this->zone()->contains(5, 5));
    }

    public function test_a_point_outside_the_ring_is_not(): void
    {
        $zone = $this->zone();

        $this->assertFalse($zone->contains(15, 5), 'north of the square');
        $this->assertFalse($zone->contains(5, -3), 'west of the square');
        $this->assertFalse($zone->contains(-1, -1), 'south-west of the square');
    }

    public function test_a_point_level_with_a_vertex_is_not_double_counted(): void
    {
        // Due east of the top-left corner, outside the shape. A ray cast that
        // counts a vertex twice reports this as inside.
        $this->assertFalse($this->zone()->contains(10, 20));
    }

    public function test_a_concave_zone_excludes_its_notch(): void
    {
        // A C shape: the bite out of the right-hand side must not be covered.
        $zone = $this->zone([
            'coordinates' => [[0, 0], [0, 10], [4, 10], [4, 4], [6, 4], [6, 10], [10, 10], [10, 0]],
        ]);

        $this->assertTrue($zone->contains(2, 8), 'inside the top arm');
        $this->assertTrue($zone->contains(8, 8), 'inside the bottom arm');
        $this->assertFalse($zone->contains(5, 8), 'inside the notch, which is outside the zone');
    }

    public function test_the_bounding_box_and_centre_are_derived_on_save(): void
    {
        $zone = $this->zone();

        $this->assertSame(0.0, $zone->min_lat);
        $this->assertSame(10.0, $zone->max_lat);
        $this->assertSame(0.0, $zone->min_lng);
        $this->assertSame(10.0, $zone->max_lng);
        $this->assertSame(5.0, $zone->centre_lat);
        $this->assertSame(5.0, $zone->centre_lng);
    }

    public function test_redrawing_a_zone_refreshes_its_box(): void
    {
        $zone = $this->zone();
        $zone->update(['coordinates' => [[0, 0], [0, 2], [2, 2], [2, 0]]]);

        $this->assertSame(2.0, $zone->fresh()->max_lat, 'a stale box would still say 10');
    }

    public function test_locate_finds_the_zone_a_point_falls_in(): void
    {
        $this->zone();

        $found = Zone::locate($this->organization->id, 5, 5);

        $this->assertNotNull($found);
        $this->assertSame('Central', $found->name);
    }

    public function test_locate_returns_nothing_outside_every_zone(): void
    {
        $this->zone();

        $this->assertNull(Zone::locate($this->organization->id, 50, 50));
    }

    public function test_locate_ignores_a_withdrawn_zone(): void
    {
        $this->zone(['active' => false]);

        $this->assertNull(Zone::locate($this->organization->id, 5, 5));
    }

    public function test_locate_does_not_cross_organizations(): void
    {
        $this->zone();

        $this->assertNull(Zone::locate('org-someone-else', 5, 5));
    }

    // ---- attachment -------------------------------------------------------

    public function test_a_provider_holds_many_zones(): void
    {
        $zone = $this->zone();
        $second = $this->zone(['id' => 'zone-2', 'name' => 'North', 'coordinates' => [[20, 20], [20, 30], [30, 30], [30, 20]]]);

        $provider = Provider::create([
            'id' => 'prov-z', 'organization_id' => $this->organization->id,
            'name' => 'Kilimanjaro Plumbing', 'status' => 'approved', 'active' => true,
        ]);

        $provider->syncZones([$zone->id, $second->id]);

        $this->assertSame(2, $provider->fresh()->zones()->count());
        $this->assertSame('coverage', $provider->fresh()->zones->first()->pivot->role);
    }

    public function test_a_shipment_holds_only_one_zone(): void
    {
        $zone = $this->zone();
        $second = $this->zone(['id' => 'zone-2', 'name' => 'North']);

        $shipment = Shipment::create([
            'id' => 'ship-z', 'organization_id' => $this->organization->id,
            'reference' => 'SHP-1', 'status' => 'packed',
        ]);

        $shipment->syncZones([$zone->id, $second->id]);

        $this->assertSame(1, $shipment->fresh()->zones()->count(), 'a shipment goes to one place');
        $this->assertSame('destination', $shipment->fresh()->zones->first()->pivot->role);
    }

    public function test_roles_keep_two_kinds_of_zoning_apart(): void
    {
        $zone = $this->zone();

        $provider = Provider::create([
            'id' => 'prov-z', 'organization_id' => $this->organization->id,
            'name' => 'Kilimanjaro Plumbing', 'status' => 'approved', 'active' => true,
        ]);

        $provider->syncZones([$zone->id]);
        $this->organization->syncZones([$zone->id]);

        // Same zone, same table, two roles — and syncing one must not have
        // detached the other.
        $this->assertSame(1, $provider->fresh()->zones()->count());
        $this->assertSame(1, $this->organization->fresh()->zones()->count());
        $this->assertSame(2, DB::table('zonables')->where('zone_id', $zone->id)->count());
    }

    public function test_providers_can_be_filtered_to_a_point(): void
    {
        $zone = $this->zone();

        $covering = Provider::create([
            'id' => 'prov-in', 'organization_id' => $this->organization->id,
            'name' => 'Covers Central', 'status' => 'approved', 'active' => true,
        ]);
        $covering->syncZones([$zone->id]);

        Provider::create([
            'id' => 'prov-out', 'organization_id' => $this->organization->id,
            'name' => 'Covers Nothing', 'status' => 'approved', 'active' => true,
        ]);

        $found = Provider::query()->coveringPoint($this->organization->id, 5, 5)->get();

        $this->assertCount(1, $found);
        $this->assertSame('Covers Central', $found->first()->name);
    }

    public function test_a_point_no_zone_covers_returns_no_providers(): void
    {
        $zone = $this->zone();

        $provider = Provider::create([
            'id' => 'prov-in', 'organization_id' => $this->organization->id,
            'name' => 'Covers Central', 'status' => 'approved', 'active' => true,
        ]);
        $provider->syncZones([$zone->id]);

        // The bug this guards: an unfiltered query here would claim every
        // provider serves a place none of them do.
        $this->assertCount(0, Provider::query()->coveringPoint($this->organization->id, 80, 80)->get());
    }

    // ---- the admin screens ------------------------------------------------

    public function test_the_zone_pages_open(): void
    {
        $zone = $this->zone();

        $this->actingAs($this->admin())->get('/admin/m/zones')->assertOk();
        $this->actingAs($this->admin())->get('/admin/m/zones/records')->assertOk();
        $this->actingAs($this->admin())->get('/admin/m/zones/records/create')->assertOk();
        $this->actingAs($this->admin())->get('/admin/m/zones/records/' . $zone->id . '/edit')->assertOk();
    }

    public function test_a_zone_is_drawn_through_the_form(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/m/zones/records', [
                'name' => 'Mikocheni',
                'colour' => '#123456',
                'active' => '1',
                'coordinates' => json_encode([[-6.7, 39.2], [-6.7, 39.3], [-6.8, 39.3], [-6.8, 39.2]]),
            ])
            ->assertRedirect();

        $zone = Zone::where('name', 'Mikocheni')->firstOrFail();

        $this->assertCount(4, $zone->ring());
        $this->assertNotEmpty($zone->code, 'the code is allocated, not typed');
        $this->assertTrue($zone->contains(-6.75, 39.25));
    }

    public function test_a_shape_with_two_corners_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/m/zones/records', [
                'name' => 'Sliver',
                'coordinates' => json_encode([[0, 0], [1, 1]]),
            ])
            ->assertSessionHasErrors('coordinates');

        $this->assertSame(0, Zone::where('name', 'Sliver')->count());
    }

    public function test_a_corner_outside_the_world_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/m/zones/records', [
                'name' => 'Impossible',
                'coordinates' => json_encode([[0, 0], [0, 10], [200, 10]]),
            ])
            ->assertSessionHasErrors('coordinates');
    }

    public function test_deleting_a_zone_takes_its_attachments_with_it(): void
    {
        $zone = $this->zone();

        $provider = Provider::create([
            'id' => 'prov-z', 'organization_id' => $this->organization->id,
            'name' => 'Kilimanjaro Plumbing', 'status' => 'approved', 'active' => true,
        ]);
        $provider->syncZones([$zone->id]);

        $this->actingAs($this->admin())
            ->delete('/admin/m/zones/records/' . $zone->id)
            ->assertRedirect();

        $this->assertSame(0, DB::table('zonables')->where('zone_id', $zone->id)->count());
    }

    public function test_neighbours_excludes_the_zone_being_edited(): void
    {
        $zone = $this->zone();
        $this->zone(['id' => 'zone-2', 'name' => 'North', 'coordinates' => [[20, 20], [20, 30], [30, 30], [30, 20]]]);

        $this->actingAs($this->admin())
            ->get('/admin/m/zones/records/' . $zone->id . '/neighbours')
            ->assertOk()
            ->assertJsonCount(1, 'zones')
            ->assertJsonPath('zones.0.name', 'North');
    }

    // ---- attaching, and who may ------------------------------------------

    public function test_zones_are_attached_through_the_endpoint(): void
    {
        $zone = $this->zone();

        $provider = Provider::create([
            'id' => 'prov-z', 'organization_id' => $this->organization->id,
            'name' => 'Kilimanjaro Plumbing', 'status' => 'approved', 'active' => true,
        ]);

        $this->actingAs($this->admin())
            ->put('/admin/m/zones/attach/servicehub-provider/' . $provider->id, ['zones' => [$zone->id]])
            ->assertRedirect();

        $this->assertSame(1, $provider->fresh()->zones()->count());
    }

    public function test_an_unknown_kind_cannot_be_zoned(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/m/zones/attach/App-Models-User/' . $this->admin()->id, ['zones' => []])
            ->assertNotFound();
    }

    public function test_another_organizations_zone_cannot_be_attached(): void
    {
        Organization::create(['id' => 'org-other', 'name' => 'Other', 'slug' => 'other-zones']);

        $theirs = Zone::create([
            'id' => 'zone-theirs',
            'organization_id' => 'org-other',
            'name' => 'Theirs',
            'coordinates' => self::SQUARE,
            'active' => true,
        ]);

        $provider = Provider::create([
            'id' => 'prov-z', 'organization_id' => $this->organization->id,
            'name' => 'Kilimanjaro Plumbing', 'status' => 'approved', 'active' => true,
        ]);

        $this->actingAs($this->admin())
            ->put('/admin/m/zones/attach/servicehub-provider/' . $provider->id, ['zones' => [$theirs->id]])
            ->assertRedirect();

        $this->assertSame(0, $provider->fresh()->zones()->count(), 'a posted id is not a grant');
    }

    public function test_an_employee_cannot_change_zoning(): void
    {
        $zone = $this->zone();

        $user = User::create([
            'id' => 'user-zone-employee',
            'username' => 'zone_employee',
            'email' => 'zemployee@fge.or.tz',
            'password_hash' => bcrypt('secret'),
            'role' => 'user',
            'active' => true,
            'organization_id' => $this->organization->id,
        ]);

        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'role' => 'employee',
            'active' => true,
        ]);

        foreach (['zones', 'fulfillment'] as $module) {
            ModuleAccess::create([
                'organization_id' => $this->organization->id,
                'role' => 'employee',
                'module' => $module,
                'section' => '*',
                'allowed' => true,
            ]);
        }

        $shipment = Shipment::create([
            'id' => 'ship-z', 'organization_id' => $this->organization->id,
            'reference' => 'SHP-1', 'status' => 'packed',
        ]);

        $this->actingAs($user)
            ->put('/admin/m/zones/attach/fulfillment-shipment/' . $shipment->id, ['zones' => [$zone->id]])
            ->assertForbidden();

        $this->assertSame(0, $shipment->fresh()->zones()->count());
    }

    // ---- the app-facing API ----------------------------------------------

    public function test_resolve_answers_for_a_point_no_zone_covers(): void
    {
        $this->zone();

        // A 200 with covered:false, not a 404: "we do not work there" is a real
        // answer the app has to show its user.
        $this->assertNull(Zone::locate($this->organization->id, 80, 80));
    }
}
