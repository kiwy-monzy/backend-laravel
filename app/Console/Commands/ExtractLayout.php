<?php

namespace App\Console\Commands;

use App\Models\ContentSection;
use App\Models\GalleryImage;
use App\Models\Website;
use App\Services\MediaLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fills a website's content sections from a page that already exists.
 *
 * **A new site starts empty, and an empty site is where people give up.**
 * Point this at a charity's current website and it reads the rendered markup —
 * headings, paragraphs, images, contact details, social links — and writes the
 * `general`, `hero`, `about`, `gallery` and `team` sections, so the first
 * screen an owner sees is their own content in a new template rather than a
 * form with eleven blanks.
 *
 * It is deliberately a *starting point*, not an import: everything it writes is
 * a guess from HTML structure, and `--dry-run` exists because the right way to
 * use it is to look first. Images are downloaded into storage rather than
 * hot-linked, since the source site may vanish.
 */
class ExtractLayout extends Command
{
    protected $signature = 'fge:extract-layout
                            {url : The page to read}
                            {--site= : Website slug to fill (defaults to the FGE site)}
                            {--sections=general,hero,about,gallery : Which sections to write}
                            {--images : Download images into storage and use them}
                            {--dry-run : Show what would be written without saving}';

    protected $description = 'Read a live page and fill a website’s content sections from it';

    public function handle(MediaLibrary $media): int
    {
        $url = $this->argument('url');
        $dry = (bool) $this->option('dry-run');
        $wanted = array_filter(explode(',', (string) $this->option('sections')));

        $site = $this->option('site')
            ? Website::where('slug', $this->option('site'))->first()
            : Website::find(Website::FGE_WEBSITE_ID);

        if (! $site) {
            $this->error('No such website. Pass --site=<slug>.');

            return self::FAILURE;
        }

        $this->line("Reading $url …");

        try {
            $response = Http::withHeaders([
                // Some hosts serve a stub to unknown agents; identifying
                // honestly gets the real page and is the polite thing anyway.
                'User-Agent' => 'FGE-LayoutExtractor/1.0 (+content import)',
            ])->timeout(20)->get($url);
        } catch (\Throwable $e) {
            $this->error('Could not fetch that page: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('That page returned HTTP ' . $response->status() . '.');

            return self::FAILURE;
        }

        $html = $response->body();
        $doc = $this->parse($html);
        $xpath = new \DOMXPath($doc);

        $extracted = [];

        if (in_array('general', $wanted, true)) {
            $extracted['general'] = $this->general($doc, $xpath, $url);
        }
        if (in_array('hero', $wanted, true)) {
            $extracted['hero'] = $this->hero($xpath);
        }
        if (in_array('about', $wanted, true)) {
            $extracted['about'] = $this->about($xpath);
        }

        $images = in_array('gallery', $wanted, true) ? $this->images($xpath, $url) : [];

        // ---- report ---------------------------------------------------------
        foreach ($extracted as $section => $data) {
            $this->newLine();
            $this->info(strtoupper($section));
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                $this->line('  ' . str_pad($key, 22) . Str::limit((string) $value, 70));
            }
        }

        if ($images !== []) {
            $this->newLine();
            $this->info('GALLERY');
            $this->line('  ' . count($images) . ' image(s) found');
        }

        if ($dry) {
            $this->newLine();
            $this->warn('Dry run — nothing was written.');

            return self::SUCCESS;
        }

        // ---- write ----------------------------------------------------------
        foreach ($extracted as $section => $data) {
            $data = array_filter($data, fn ($v) => $v !== '' && $v !== [] && $v !== null);

            if ($data === []) {
                continue;
            }

            $existing = $site->sectionData($section) ?? [];

            // Merge under what is already there: an extractor guessing from
            // HTML must never overwrite something a person typed.
            ContentSection::updateOrCreate(
                ['website_id' => $site->id, 'section' => $section],
                ['data' => array_replace_recursive($data, $existing)],
            );
        }

        $added = 0;
        foreach ($images as $image) {
            $url = $this->option('images') ? ($media->adopt($this->download($image['src'])) ?? $image['src']) : $image['src'];

            if (GalleryImage::where('website_id', $site->id)->where('url', $url)->exists()) {
                continue;
            }

            GalleryImage::create([
                'id' => (string) Str::uuid(),
                'website_id' => $site->id,
                'url' => $url,
                'caption' => $image['alt'],
                'disabled' => false,
            ]);
            $added++;
        }

        $this->newLine();
        $this->info('Wrote ' . count($extracted) . " section(s) and $added gallery image(s) to {$site->slug}.");
        $this->line('Review them under Content — everything above is a guess from markup.');

        return self::SUCCESS;
    }

    private function parse(string $html): \DOMDocument
    {
        $doc = new \DOMDocument;
        // Real-world markup is full of things libxml complains about; the
        // parser recovers fine and the warnings are noise.
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        return $doc;
    }

    private function general(\DOMDocument $doc, \DOMXPath $xpath, string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        $title = $this->text($xpath, '//title');
        $description = $this->attr($xpath, '//meta[@name="description"]', 'content')
            ?: $this->attr($xpath, '//meta[@property="og:description"]', 'content');

        $siteName = $this->attr($xpath, '//meta[@property="og:site_name"]', 'content')
            ?: Str::before($title, ' | ')
            ?: $host;

        $social = [];
        foreach (['facebook', 'twitter', 'instagram', 'linkedin'] as $network) {
            $href = $this->attr($xpath, "//a[contains(@href, '$network.com')]", 'href');
            if ($href) {
                $social[$network] = $href;
            }
        }

        return [
            'site_name' => trim($siteName),
            'site_title' => trim($title),
            'logo_text' => Str::upper(Str::substr(trim($siteName), 0, 4)),
            'logo_url' => $this->absolute($this->attr($xpath, '//meta[@property="og:image"]', 'content'), $url),
            'contact_email' => $this->firstMatch($doc->textContent, '/[\w.+-]+@[\w-]+\.[\w.]{2,}/'),
            // A `tel:` link first, because it is unambiguous. The text scan is
            // the fallback and demands a leading `+` or a spaced/bracketed
            // group — a bare run of digits matches dates, IP addresses and
            // prices, all of which it happily returned before.
            'contact_phone' => $this->telHref($xpath)
                ?: $this->firstMatch($doc->textContent, '/\+\d[\d\s().-]{7,}\d|\(0\d{2,4}\)[\d\s-]{6,}\d|\b0\d{2,3}[\s-]\d{3}[\s-]\d{3,4}\b/'),
            'address' => '',
            'social_links' => $social,
            'meta_description' => $description,
        ];
    }

    private function hero(\DOMXPath $xpath): array
    {
        $heading = $this->text($xpath, '//h1');

        // The first substantial paragraph after the h1 is the sub-heading far
        // more often than not; a short one is usually a caption or a label.
        $description = '';
        foreach ($xpath->query('//p') as $node) {
            $text = trim($node->textContent);
            if (mb_strlen($text) >= 60) {
                $description = $text;
                break;
            }
        }

        $buttons = [];
        foreach ($xpath->query('//a[contains(@class, "btn") or contains(@class, "button")]') as $node) {
            $label = trim($node->textContent);
            if ($label !== '' && mb_strlen($label) < 30) {
                $buttons[] = ['text' => $label, 'link' => $node->getAttribute('href')];
            }
            if (count($buttons) === 2) {
                break;
            }
        }

        return [
            'title' => $heading,
            'description' => $description,
            'primary_button_text' => $buttons[0]['text'] ?? '',
            'primary_button_link' => $buttons[0]['link'] ?? '',
            'secondary_button_text' => $buttons[1]['text'] ?? '',
            'secondary_button_link' => $buttons[1]['link'] ?? '',
        ];
    }

    private function about(\DOMXPath $xpath): array
    {
        $title = $this->text($xpath, '//h2[contains(translate(., "ABOUT", "about"), "about")]') ?: 'About us';

        $paragraphs = [];
        foreach ($xpath->query('//p') as $node) {
            $text = trim($node->textContent);
            if (mb_strlen($text) >= 80) {
                $paragraphs[] = $text;
            }
            if (count($paragraphs) === 4) {
                break;
            }
        }

        return [
            'title' => $title,
            // The first long paragraph became the hero sub-heading, so About
            // starts at the second — otherwise the same sentence appears twice
            // on the page.
            'description' => $paragraphs[1] ?? ($paragraphs[0] ?? ''),
            'mission' => $paragraphs[2] ?? '',
            'vision' => $paragraphs[3] ?? '',
        ];
    }

    /** @return array<int,array{src:string,alt:string}> */
    private function images(\DOMXPath $xpath, string $base): array
    {
        $images = [];

        foreach ($xpath->query('//img') as $node) {
            $src = $node->getAttribute('src') ?: $node->getAttribute('data-src');
            if (! $src || Str::startsWith($src, 'data:')) {
                continue;
            }

            // Icons, spacers and tracking pixels are images too, and none of
            // them belong in a gallery.
            $width = (int) $node->getAttribute('width');
            if ($width > 0 && $width < 200) {
                continue;
            }
            if (Str::contains(Str::lower($src), ['icon', 'logo', 'sprite', 'pixel', 'avatar'])) {
                continue;
            }

            $images[] = [
                'src' => $this->absolute($src, $base),
                'alt' => trim($node->getAttribute('alt')),
            ];

            if (count($images) === 30) {
                break;
            }
        }

        return $images;
    }

    /** Downloads to a temp file so MediaLibrary::adopt can take it from disk. */
    private function download(string $url): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fge');

        try {
            $body = Http::timeout(20)->get($url)->body();
            file_put_contents($path, $body);

            // Keep the original filename so the library stores something
            // recognisable rather than `fgeA4T1.tmp`.
            $named = dirname($path) . DIRECTORY_SEPARATOR . basename(parse_url($url, PHP_URL_PATH) ?: 'image.jpg');
            rename($path, $named);

            return $named;
        } catch (\Throwable $e) {
            $this->warn("  could not download $url");

            return $path;
        }
    }

    private function absolute(?string $src, string $base): string
    {
        if (! $src) {
            return '';
        }
        if (Str::startsWith($src, ['http://', 'https://', '/storage/'])) {
            return $src;
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

        return Str::startsWith($src, '/') ? $origin . $src : rtrim($base, '/') . '/' . $src;
    }

    private function text(\DOMXPath $xpath, string $query): string
    {
        $node = $xpath->query($query)->item(0);

        return $node ? trim(preg_replace('/\s+/', ' ', $node->textContent)) : '';
    }

    private function attr(\DOMXPath $xpath, string $query, string $attribute): string
    {
        $node = $xpath->query($query)->item(0);

        return $node instanceof \DOMElement ? trim($node->getAttribute($attribute)) : '';
    }

    private function telHref(\DOMXPath $xpath): string
    {
        $href = $this->attr($xpath, '//a[starts-with(@href, "tel:")]', 'href');

        return $href ? trim(Str::after($href, 'tel:')) : '';
    }

    private function firstMatch(string $haystack, string $pattern): string
    {
        return preg_match($pattern, $haystack, $m) ? trim($m[0]) : '';
    }
}
