<?php

namespace Modules\Website\Http\Controllers;

use App\Http\Controllers\Web\AdminController;
use App\Models\ContentSection;
use App\Models\Website;
use App\Services\MediaLibrary;
use App\Support\ContentSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The eleven content sections that make up a public site.
 *
 * **Two editors over one piece of data.** The form is what anyone should use:
 * real fields, an image picker, repeaters for the lists. The raw JSON editor
 * stays behind a tab because the sections are free-form by design — a template
 * can introduce a field without a migration, and the form would otherwise be a
 * ceiling rather than a convenience.
 *
 * The form never *filters* the data. Keys the schema does not know about are
 * merged back untouched on save, so editing the hero through the form cannot
 * silently drop a field some template is reading.
 */
class ContentController extends AdminController
{
    public function __construct(private MediaLibrary $media) {}

    public function index()
    {
        $site = $this->site();

        return view('website::content.index', [
            'site' => $site,
            'sections' => collect(Website::SECTIONS)->map(fn (string $key) => [
                'key' => $key,
                'label' => $this->label($key),
                'filled' => filled($site?->sectionData($key)),
                'managed' => ContentSchema::isManaged($key),
                // Identity, contact and social links now live on the
                // organization profile — one place, edited once, served to
                // every website the tenant owns.
                'profile' => $key === 'general',
            ]),
        ]);
    }

    public function edit(Request $request, string $section)
    {
        $this->assertSection($section);

        if ($section === 'general') {
            return redirect()->route('organization.edit')
                ->with('status', __('The site identity now lives on the Organization profile.'));
        }

        $site = $this->site();
        $locale = $this->resolveLocale($site, $request->query('lang'));
        $data = $site?->sectionData($section, $locale) ?? [];

        return view('website::content.edit', [
            'site' => $site,
            'section' => $section,
            'label' => $this->label($section),
            'schema' => ContentSchema::for($section),
            'data' => $data,
            'locale' => $locale,
            'languages' => $site?->offeredLanguages() ?? ['en'],
            'mode' => $request->query('mode') === 'json' || ! ContentSchema::hasSchema($section)
                ? 'json'
                : 'form',
            'json' => json_encode(
                $data ?: new \stdClass,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            'previewPage' => $this->previewPage($section),
        ]);
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        $this->assertSection($section);

        if ($section === 'general') {
            return redirect()->route('organization.edit')
                ->with('status', __('The site identity now lives on the Organization profile.'));
        }

        $site = $this->site();

        if (! $site) {
            return back()->with('error', __('No website is selected.'));
        }

        $locale = $this->resolveLocale($site, $request->input('lang'));
        $existing = $site->sectionData($section, $locale) ?? [];

        if ($request->input('editor') === 'json') {
            $decoded = json_decode((string) $request->input('data'), true);

            if (! is_array($decoded)) {
                return back()->withInput()->withErrors([
                    'data' => __('That is not valid JSON: :error', ['error' => json_last_error_msg()]),
                ]);
            }
        } else {
            $decoded = $this->merge($existing, $this->fromForm($request, $section), $section);
        }

        $converted = 0;
        $decoded = $this->media->materialiseDeep(
            $decoded, $converted, $section, $this->organizationId(), 'website',
        );

        ContentSection::updateOrCreate(
            ['website_id' => $site->id, 'section' => $section, 'locale' => $locale],
            ['data' => $decoded],
        );

        $message = __('Section saved.');
        if ($converted > 0) {
            $message .= ' '.trans_choice(
                ':count embedded image was written to storage.|:count embedded images were written to storage.',
                $converted,
                ['count' => $converted],
            );
        }

        return back()->with('status', $message);
    }

    /**
     * Fold the form's values into what is already stored.
     *
     * Deep-merged, so a key the schema has no field for survives editing —
     * except for the repeater lists, which are **replaced outright**.
     * `array_replace_recursive` merges arrays by key, and for a list that
     * means index 3 of the old value survives when the new one has three rows:
     * deleting a team member would silently do nothing.
     */
    private function merge(array $existing, array $form, string $section): array
    {
        $merged = array_replace_recursive($existing, $form);

        foreach (ContentSchema::for($section) as $field) {
            if ($field['type'] === 'repeat') {
                $merged[$field['name']] = $form[$field['name']] ?? [];
            }
        }

        return $merged;
    }

    /**
     * Assemble the section from the posted form.
     *
     * Repeaters post as `items[0][title]`; rows whose every value is blank are
     * dropped, which is how the "add row" button can leave an empty row behind
     * without it becoming a blank card on the public site.
     */
    private function fromForm(Request $request, string $section): array
    {
        $out = [];

        foreach (ContentSchema::for($section) as $field) {
            $name = $field['name'];

            if ($field['type'] === 'repeat') {
                $rows = [];
                foreach ((array) $request->input($name, []) as $row) {
                    $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, (array) $row);

                    if (collect($row)->filter(fn ($v) => $v !== '' && $v !== null)->isEmpty()) {
                        continue;
                    }

                    // Ids are how a blog post keeps its URL when the list is
                    // reordered, so an existing one is carried through and a
                    // new row gets one rather than being matched by position.
                    // `??` as well as `?:` because a repeater rendered without
                    // a hidden id — the stats rows — posts no key at all.
                    $row['id'] = ($row['id'] ?? '') ?: (string) ((int) (microtime(true) * 1000) + count($rows));

                    foreach ($field['fields'] as $sub) {
                        if ($sub['type'] === 'checkbox') {
                            $row[$sub['name']] = (bool) ($row[$sub['name']] ?? false);
                        }
                    }

                    $rows[] = $row;
                }
                $out[$name] = $rows;

                continue;
            }

            if ($field['type'] === 'toggles') {
                $submitted = (array) $request->input($name, []);
                foreach ($field['keys'] as $key) {
                    Arr::set($out, "$name.$key", (bool) ($submitted[$key] ?? false));
                }

                continue;
            }

            if ($field['type'] === 'checkbox') {
                Arr::set($out, $name, $request->boolean($name));

                continue;
            }

            $value = $request->input($name);
            if ($value !== null) {
                Arr::set($out, $name, is_string($value) ? trim($value) : $value);
            }
        }

        return $out;
    }

    /** Which public page best shows a given section, for the preview pane. */
    private function previewPage(string $section): string
    {
        return match ($section) {
            'about' => 'about',
            'projects' => 'projects',
            'team' => 'team',
            'gallery' => 'gallery',
            'blog' => 'blog',
            'events' => 'events',
            'donate' => 'donate',
            default => 'home',
        };
    }

    /** The locale being edited, capped to what the site offers. */
    private function resolveLocale(?Website $site, ?string $requested): string
    {
        $offered = $site?->offeredLanguages() ?? ['en'];

        return in_array($requested, $offered, true) ? $requested : ($site?->default_language ?: 'en');
    }

    private function assertSection(string $section): void
    {
        if (! in_array($section, Website::SECTIONS, true)) {
            throw new NotFoundHttpException("Unknown section: $section");
        }
    }

    private function label(string $key): string
    {
        return match ($key) {
            'general' => __('General'),
            'hero' => __('Hero'),
            'about' => __('About'),
            'projects' => __('Projects'),
            'team' => __('Team'),
            'gallery' => __('Gallery'),
            'blog' => __('Blog'),
            'events' => __('Events'),
            'theme' => __('Theme'),
            'chatbot' => __('Chatbot'),
            'donate' => __('Donate'),
            default => ucfirst($key),
        };
    }
}
