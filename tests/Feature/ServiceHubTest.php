<?php

namespace Tests\Feature;

use App\Models\ModuleAccess;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ServiceHub\Models\Booking;
use Modules\ServiceHub\Models\Provider;
use Modules\ServiceHub\Models\Service;
use Modules\ServiceHub\Models\ServiceRequest;
use Tests\TestCase;

/**
 * Service Hub: providers, the catalogue, requests and the bookings they become.
 *
 * The three things worth pinning down are the ones a later change could break
 * silently — the commission that is stored rather than recomputed, the
 * conversion that must not run twice, and the organization scoping that stands
 * between two tenants' customer lists.
 */
class ServiceHubTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'id' => 'org-servicehub',
            'name' => 'FGE',
            'slug' => 'fge-servicehub',
            'currency' => 'TZS',
            'plan' => 'professional',
            'subscription_status' => 'active',
            'trial_ends_at' => now()->addDays(30),
        ]);

        ModuleAccess::create([
            'organization_id' => $this->organization->id,
            'role' => 'admin',
            'module' => 'servicehub',
            'section' => '*',
            'allowed' => true,
        ]);
    }

    private function admin(): User
    {
        $user = User::firstOrCreate(['username' => 'hub_admin'], [
            'id' => 'user-servicehub-admin',
            'email' => 'hub@fge.or.tz',
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

    private function provider(array $attributes = []): Provider
    {
        return Provider::create($attributes + [
            'id' => 'prov-1',
            'organization_id' => $this->organization->id,
            'code' => 'SP-0001',
            'name' => 'Kilimanjaro Plumbing',
            'phone' => '+255700000001',
            'zone' => 'Kinondoni',
            'status' => 'approved',
            'commission_percent' => 12.5,
            'active' => true,
        ]);
    }

    private function service(Provider $provider): Service
    {
        return Service::create([
            'id' => 'svc-1',
            'organization_id' => $this->organization->id,
            'provider_id' => $provider->id,
            'name' => 'Blocked drain clearing',
            'category' => 'Plumbing',
            'price_minor' => 8_000_000,
            'duration_minutes' => 90,
            'active' => true,
        ]);
    }

    private function request(array $attributes = []): ServiceRequest
    {
        return ServiceRequest::create($attributes + [
            'id' => 'req-1',
            'organization_id' => $this->organization->id,
            'reference' => 'SR-000001',
            'customer' => 'Neema Joseph',
            'phone' => '+255711222333',
            'address' => 'Mikocheni B',
            'zone' => 'Kinondoni',
            'budget_minor' => 5_000_000,
            'preferred_at' => now()->addDay()->setTime(10, 0),
            'status' => 'pending',
        ]);
    }

    public function test_the_module_pages_open_for_an_admin(): void
    {
        $this->actingAs($this->admin())->get('/admin/m/servicehub')->assertOk();
        $this->actingAs($this->admin())->get('/admin/m/servicehub/providers')->assertOk();
        $this->actingAs($this->admin())->get('/admin/m/servicehub/services')->assertOk();
        $this->actingAs($this->admin())->get('/admin/m/servicehub/requests')->assertOk();
        $this->actingAs($this->admin())->get('/admin/m/servicehub/bookings')->assertOk();
    }

    public function test_a_provider_is_created_with_a_generated_code(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/m/servicehub/providers', [
                'name' => 'Dodoma Electrics',
                'phone' => '+255755000000',
                'status' => 'approved',
                'commission_percent' => 10,
                'rating' => 0,
                'active' => '1',
            ])
            ->assertRedirect();

        $provider = Provider::where('name', 'Dodoma Electrics')->firstOrFail();

        $this->assertSame($this->organization->id, $provider->organization_id);
        $this->assertNotEmpty($provider->code, 'The provider code is allocated, not typed.');
    }

    public function test_assigning_a_provider_moves_a_pending_request_on(): void
    {
        $provider = $this->provider();
        $request = $this->request();

        $this->actingAs($this->admin())
            ->put('/admin/m/servicehub/requests/' . $request->id, [
                'customer' => 'Neema Joseph',
                'provider_id' => $provider->id,
                'service_id' => '',
                'status' => 'pending',
                'budget' => '50000',
            ])
            ->assertRedirect();

        $this->assertSame('assigned', $request->fresh()->status);
    }

    public function test_a_request_becomes_a_booking_carrying_its_details_across(): void
    {
        $provider = $this->provider();
        $service = $this->service($provider);
        $request = $this->request([
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'status' => 'assigned',
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/m/servicehub/requests/' . $request->id . '/convert')
            ->assertRedirect();

        $booking = Booking::where('request_id', $request->id)->firstOrFail();

        $this->assertSame('booked', $request->fresh()->status);
        $this->assertSame($provider->id, $booking->provider_id);
        $this->assertSame('Neema Joseph', $booking->customer);
        $this->assertSame('Mikocheni B', $booking->address);
        $this->assertSame(90, $booking->duration_minutes);

        // The catalogue price wins over the customer's guess at a budget.
        $this->assertSame(8_000_000, $booking->amount_minor);

        // 12.5% of 80,000.00, stored rather than recomputed on read.
        $this->assertSame(1_000_000, $booking->commission_minor);
        $this->assertSame(7_000_000, $booking->payoutMinor());
    }

    public function test_a_request_without_a_provider_is_not_booked(): void
    {
        $request = $this->request();

        $this->actingAs($this->admin())
            ->post('/admin/m/servicehub/requests/' . $request->id . '/convert')
            ->assertRedirect();

        $this->assertSame(0, Booking::where('request_id', $request->id)->count());
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_converting_twice_does_not_make_a_second_booking(): void
    {
        $provider = $this->provider();
        $this->service($provider);
        $request = $this->request(['provider_id' => $provider->id, 'service_id' => 'svc-1', 'status' => 'assigned']);

        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/m/servicehub/requests/' . $request->id . '/convert');
        $this->actingAs($admin)->post('/admin/m/servicehub/requests/' . $request->id . '/convert');

        $this->assertSame(1, Booking::where('request_id', $request->id)->count());
    }

    public function test_a_commission_left_blank_is_taken_from_the_providers_rate(): void
    {
        $provider = $this->provider();

        $this->actingAs($this->admin())
            ->post('/admin/m/servicehub/bookings', [
                'customer' => 'Neema Joseph',
                'provider_id' => $provider->id,
                'service_id' => '',
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i'),
                'duration_minutes' => 60,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'amount' => '40000',
                'commission' => '0',
            ])
            ->assertRedirect();

        $booking = Booking::where('customer', 'Neema Joseph')->firstOrFail();

        $this->assertSame(4_000_000, $booking->amount_minor);
        $this->assertSame(500_000, $booking->commission_minor);
    }

    public function test_another_organizations_records_are_not_reachable(): void
    {
        $other = Organization::create(['id' => 'org-other', 'name' => 'Other', 'slug' => 'other']);

        $theirs = Provider::create([
            'id' => 'prov-theirs',
            'organization_id' => $other->id,
            'name' => 'Someone Else Ltd',
            'status' => 'approved',
            'active' => true,
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/m/servicehub/providers/' . $theirs->id . '/edit')
            ->assertNotFound();

        $this->actingAs($this->admin())
            ->get('/admin/m/servicehub/providers/data')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Someone Else Ltd']);
    }

    public function test_an_employee_may_not_delete(): void
    {
        $user = User::create([
            'id' => 'user-servicehub-employee',
            'username' => 'hub_employee',
            'email' => 'employee@fge.or.tz',
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

        ModuleAccess::create([
            'organization_id' => $this->organization->id,
            'role' => 'employee',
            'module' => 'servicehub',
            'section' => '*',
            'allowed' => true,
        ]);

        $provider = $this->provider();

        $this->actingAs($user)
            ->delete('/admin/m/servicehub/providers/' . $provider->id)
            ->assertForbidden();

        $this->assertNotNull($provider->fresh());
    }
}
