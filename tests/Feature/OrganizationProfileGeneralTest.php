<?php

namespace Tests\Feature;

use App\Models\ContentSection;
use App\Models\Organization;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The website's `general` section — identity, contact, social links — lives on
 * the organization profile now, and every surface (public templates, the
 * `GetWebsite` API, the legacy section-write endpoints) reads it from there.
 */
class OrganizationProfileGeneralTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Website $website;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'id' => 'org-1',
            'name' => 'FGE',
            'slug' => 'fge-test',
            'email' => 'info@fge.or.tz',
            'general' => [
                'site_name' => 'FGE',
                'site_title' => 'Foundation for Gender Equality',
                'logo_url' => '/storage/uploads/fge.png',
                'contact_email' => 'info@fge.or.tz',
                'contact_phone' => '+255 762 060 160',
                'address' => 'Mkonze Dodoma – Tanzania',
                'social_links' => ['facebook' => 'https://facebook.com/fge', 'twitter' => '#'],
                'theme_color' => 'green',
                'visibility' => ['hero' => true, 'donate' => false],
            ],
        ]);

        $this->website = Website::create([
            'id' => Website::FGE_WEBSITE_ID,
            'owner_id' => $this->owner()->id,
            'organization_id' => $this->organization->id,
            'name' => 'FGE',
            'slug' => 'fge-test',
            'is_active' => true,
            'template' => 'template1',
        ]);
    }

    private function owner(): User
    {
        return User::firstOrCreate(['username' => 'fge_owner'], [
            'id' => 'user-owner',
            'email' => 'owner@fge.or.tz',
            'password_hash' => bcrypt('secret'),
            'role' => 'owner',
            'active' => true,
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_site_data_serves_general_from_the_organization_profile(): void
    {
        $data = $this->website->siteData();

        $this->assertSame('FGE', $data['general']['site_name']);
        $this->assertSame('info@fge.or.tz', $data['general']['contact_email']);
        $this->assertSame('https://facebook.com/fge', $data['general']['social_links']['facebook']);
        $this->assertSame('green', $data['general']['theme_color']);
        $this->assertFalse($data['general']['visibility']['donate']);
    }

    public function test_profile_wins_field_by_field_over_the_stored_section(): void
    {
        ContentSection::create([
            'website_id' => $this->website->id,
            'section' => 'general',
            'locale' => 'en',
            'data' => [
                'site_name' => 'Old Name',
                'contact_email' => 'old@fge.or.tz',
                // A key the profile does not carry falls back to the section.
                'visibility' => ['blog' => true],
            ],
        ]);

        $general = $this->website->siteData()['general'];

        $this->assertSame('FGE', $general['site_name']);
        $this->assertSame('info@fge.or.tz', $general['contact_email']);
        $this->assertSame('green', $general['theme_color']);
        $this->assertSame('Mkonze Dodoma – Tanzania', $general['address']);
    }

    public function test_get_website_api_returns_the_profile_as_general(): void
    {
        $response = $this->postJson('/GetWebsite', []);

        $response->assertOk()->assertJsonPath('success', true);
        $response->assertJsonPath('website.website_data.general.site_name', 'FGE');
        $response->assertJsonPath('website.website_data.general.contact_email', 'info@fge.or.tz');
        $response->assertJsonPath('website.website_data.general.social_links.facebook', 'https://facebook.com/fge');
    }

    public function test_update_section_writes_general_to_the_profile(): void
    {
        $user = $this->owner();
        $token = app(\App\Services\JwtService::class)->issue($user->id, $user->username, $user->role);

        $this->postJson('/UpdateSection', [
            'section' => 'general',
            'data' => [
                'contact_email' => 'new@fge.or.tz',
                'site_name' => 'FGE Tanzania',
            ],
        ], ['Authorization' => 'Bearer ' . $token])->assertOk();

        $this->organization->refresh();

        $this->assertSame('new@fge.or.tz', $this->organization->general['contact_email']);
        $this->assertSame('FGE Tanzania', $this->organization->general['site_name']);
        // Merged over the profile, so untouched keys survive.
        $this->assertSame('green', $this->organization->general['theme_color']);
        $this->assertFalse($this->organization->general['visibility']['donate']);
        // And no content-section row was created for it.
        $this->assertDatabaseMissing('content_sections', ['section' => 'general']);
    }

    public function test_profile_form_save_persists_unchecked_toggles(): void
    {
        $member = User::firstOrCreate(['username' => 'fge_owner'], [
            'id' => 'user-owner',
            'email' => 'owner@fge.or.tz',
            'password_hash' => bcrypt('secret'),
            'role' => 'owner',
            'active' => true,
            'organization_id' => $this->organization->id,
        ]);

        \App\Models\OrganizationMember::firstOrCreate([
            'organization_id' => $this->organization->id,
            'user_id' => $member->id,
        ], ['role' => 'admin', 'active' => true]);

        // A real form submission: an unchecked toggle posts `0` (its hidden
        // twin), a checked one posts `0` followed by `1` (last wins).
        $this->actingAs($member)
            ->put('/admin/organization', [
                'name' => 'FGE',
                'email' => 'info@fge.or.tz',
                'phone' => '+255 762 060 160',
                'address' => 'Mkonze Dodoma – Tanzania',
                'country' => 'TZ',
                'currency' => 'TZS',
                'site_name' => 'FGE',
                'site_title' => 'Foundation for Gender Equality',
                'logo_text' => 'FGE',
                'visibility' => [
                    'hero' => '1',
                    'about' => '1',
                    'projects' => '1',
                    'services' => '1',
                    'achievements' => '1',
                    'team' => '1',
                    'gallery' => '1',
                    'volunteer' => '1',
                    'donate' => '0',
                    'footer' => '1',
                ],
            ])->assertRedirect('/');

        $this->organization->refresh();

        $this->assertTrue($this->organization->general['visibility']['hero']);
        // The exact regression: an unchecked box must persist as false.
        $this->assertFalse($this->organization->general['visibility']['donate']);
        $this->assertSame('info@fge.or.tz', $this->organization->general['contact_email']);
    }

    public function test_organization_without_profile_falls_back_to_the_section(): void
    {
        $bare = Organization::create(['id' => 'org-2', 'name' => 'Knowlia', 'slug' => 'knowlia-test']);
        $site = Website::create([
            'id' => 'site-2',
            'owner_id' => $this->owner()->id,
            'organization_id' => $bare->id,
            'name' => 'Knowlia',
            'slug' => 'knowlia-test',
            'is_active' => true,
            'template' => 'template1',
        ]);

        ContentSection::create([
            'website_id' => $site->id,
            'section' => 'general',
            'locale' => 'en',
            'data' => ['site_name' => 'Knowlia', 'contact_email' => 'hello@knowlia.co.tz'],
        ]);

        $general = $site->siteData()['general'];

        $this->assertSame('Knowlia', $general['site_name']);
        $this->assertSame('hello@knowlia.co.tz', $general['contact_email']);
    }
}
