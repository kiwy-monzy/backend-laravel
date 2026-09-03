<?php

namespace Tests\Feature;

use App\Models\ModuleAccess;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The template, palette and colour overrides moved onto the organization
 * profile: every website of a tenant renders in the profile's look, the
 * per-website columns stay only for previews, and a new site's chosen look is
 * written to the profile, not the site.
 */
class OrganizationPresentationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Website $website;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'id' => 'org-presentation',
            'name' => 'FGE',
            'slug' => 'fge-presentation',
            'template' => 'template3',
            'theme' => 'sunset',
            'theme_overrides' => ['primary' => '#ff0000'],
            'plan' => 'free_trial',
            'subscription_status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ]);

        // Mirror the seeded grant matrix so the module gate lets the owner in.
        ModuleAccess::create([
            'organization_id' => $this->organization->id,
            'role' => 'owner',
            'module' => 'website',
            'section' => '*',
            'allowed' => true,
        ]);

        $this->website = Website::create([
            'id' => 'site-presentation',
            'owner_id' => $this->owner()->id,
            'organization_id' => $this->organization->id,
            'name' => 'FGE',
            'slug' => 'fge-presentation',
            'is_active' => true,
        ]);
    }

    private function owner(): User
    {
        return User::firstOrCreate(['username' => 'fge_owner'], [
            'id' => 'user-presentation-owner',
            'email' => 'owner@fge.or.tz',
            'password_hash' => bcrypt('secret'),
            'role' => 'owner',
            'active' => true,
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_site_renders_in_the_profile_look(): void
    {
        $this->assertSame('template3', $this->website->templateKey());
        $this->assertSame('sunset', $this->website->effectiveTheme());
        $this->assertSame(['primary' => '#ff0000'], $this->website->effectiveThemeOverrides());
    }

    public function test_profile_without_a_look_falls_back_to_the_defaults(): void
    {
        $bare = Organization::create(['id' => 'org-bare', 'name' => 'Bare', 'slug' => 'bare']);
        $site = Website::create([
            'id' => 'site-bare',
            'owner_id' => $this->owner()->id,
            'organization_id' => $bare->id,
            'name' => 'Bare',
            'slug' => 'bare',
            'is_active' => true,
        ]);

        $this->assertSame('template1', $site->templateKey());
        $this->assertSame('fge', $site->effectiveTheme());
        $this->assertSame([], $site->effectiveThemeOverrides());
    }

    public function test_site_level_columns_win_for_previews(): void
    {
        $this->website->update([
            'template' => 'template5',
            'theme' => 'ocean',
            'theme_overrides' => ['secondary' => '#00ff00'],
        ]);

        $this->assertSame('template5', $this->website->templateKey());
        $this->assertSame('ocean', $this->website->effectiveTheme());
        $this->assertSame(['secondary' => '#00ff00'], $this->website->effectiveThemeOverrides());
    }

    public function test_profile_save_persists_template_theme_and_overrides(): void
    {
        $member = $this->owner();
        OrganizationMember::firstOrCreate([
            'organization_id' => $this->organization->id,
            'user_id' => $member->id,
        ], ['role' => 'admin', 'active' => true]);

        $this->actingAs($member)->put('/admin/organization', [
            'name' => 'FGE',
            'email' => 'info@fge.or.tz',
            'phone' => '+255 762 060 160',
            'address' => 'Mkonze Dodoma – Tanzania',
            'country' => 'TZ',
            'currency' => 'TZS',
            'template' => 'template4',
            'theme' => 'ocean',
            'override_primary' => '#123456',
            'override_secondary' => '#654321',
        ])->assertRedirect('/');

        $this->organization->refresh();

        $this->assertSame('template4', $this->organization->template);
        $this->assertSame('ocean', $this->organization->theme);
        $this->assertSame(
            ['primary' => '#123456', 'secondary' => '#654321'],
            $this->organization->theme_overrides
        );
    }

    public function test_new_site_writes_its_chosen_look_to_the_profile(): void
    {
        $this->actingAs($this->owner())->post('/admin/m/website/sites', [
            'name' => 'Known Point',
            'slug' => 'known-point',
            'template' => 'template2',
            'theme' => 'royal',
            'override_primary' => '#1e3a8a',
            'splash' => 'none',
            'splash_seconds' => '2',
            'default_language' => 'en',
            'languages' => ['en'],
            'robots' => 'index,follow',
        ])->assertRedirect();

        $this->organization->refresh();
        $site = Website::where('slug', 'known-point')->firstOrFail();

        $this->assertSame('template2', $this->organization->template);
        $this->assertSame('royal', $this->organization->theme);
        $this->assertSame(['primary' => '#1e3a8a'], $this->organization->theme_overrides);

        $this->assertNull($site->template, 'the profile owns the look, not the site');
        $this->assertNull($site->theme);
        $this->assertNull($site->theme_overrides);
        $this->assertSame('template2', $site->templateKey());
    }
}
