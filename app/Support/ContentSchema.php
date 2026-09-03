<?php

namespace App\Support;

/**
 * The shape of each of the eleven content sections, as editable fields.
 *
 * **The JSON editor was honest but hostile.** It let a template author add a
 * field without a migration, which is right, but it also meant anyone editing
 * a charity's hero text had to know JSON and could take the whole home page
 * down with a stray comma. This describes the same data as fields, so the
 * common case is a form — and the raw editor stays, one click away, for the
 * fields a schema has not caught up with yet.
 *
 * A section is a list of entries. `type` is either a scalar input or `repeat`,
 * which nests `fields` and edits a list of rows (team members, projects).
 * Anything present in the stored JSON but absent here is preserved untouched
 * on save; the form is a view over the data, never a filter on it.
 */
final class ContentSchema
{
    public static function for(string $section): array
    {
        return match ($section) {
            'general' => [
                ['name' => 'site_name', 'label' => 'Site name', 'type' => 'text'],
                ['name' => 'site_title', 'label' => 'Browser title', 'type' => 'text'],
                ['name' => 'logo_text', 'label' => 'Logo text', 'type' => 'text'],
                ['name' => 'logo_url', 'label' => 'Logo image', 'type' => 'image'],
                ['name' => 'contact_email', 'label' => 'Contact email', 'type' => 'email'],
                ['name' => 'contact_phone', 'label' => 'Contact phone', 'type' => 'text'],
                ['name' => 'address', 'label' => 'Address', 'type' => 'text'],
                ['name' => 'social_links.facebook', 'label' => 'Facebook', 'type' => 'url'],
                ['name' => 'social_links.twitter', 'label' => 'Twitter', 'type' => 'url'],
                ['name' => 'social_links.instagram', 'label' => 'Instagram', 'type' => 'url'],
                ['name' => 'social_links.linkedin', 'label' => 'LinkedIn', 'type' => 'url'],
                ['name' => 'visibility', 'label' => 'Show these sections', 'type' => 'toggles', 'keys' => [
                    'hero', 'about', 'projects', 'services', 'achievements',
                    'team', 'gallery', 'volunteer', 'donate', 'footer',
                ]],
            ],

            'hero' => [
                ['name' => 'badge', 'label' => 'Badge text', 'type' => 'text',
                 'help' => 'The small pill above the headline.'],
                ['name' => 'title', 'label' => 'Headline', 'type' => 'textarea'],
                ['name' => 'description', 'label' => 'Sub-heading', 'type' => 'textarea'],
                ['name' => 'primary_button_text', 'label' => 'Primary button', 'type' => 'text'],
                ['name' => 'primary_button_link', 'label' => 'Primary link', 'type' => 'text'],
                ['name' => 'secondary_button_text', 'label' => 'Secondary button', 'type' => 'text'],
                ['name' => 'secondary_button_link', 'label' => 'Secondary link', 'type' => 'text'],
                ['name' => 'background_image', 'label' => 'Background texture', 'type' => 'image'],
                ['name' => 'stats', 'label' => 'Statistics', 'type' => 'repeat', 'fields' => [
                    ['name' => 'value', 'label' => 'Number', 'type' => 'text'],
                    ['name' => 'label', 'label' => 'Caption', 'type' => 'text'],
                ]],
            ],

            'about' => [
                ['name' => 'title', 'label' => 'Heading', 'type' => 'text'],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['name' => 'mission', 'label' => 'Mission', 'type' => 'textarea'],
                ['name' => 'vision', 'label' => 'Vision', 'type' => 'textarea'],
            ],

            'projects' => [
                ['name' => 'items', 'label' => 'Projects', 'type' => 'repeat', 'fields' => [
                    ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text'],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                    ['name' => 'image', 'label' => 'Image', 'type' => 'image'],
                    ['name' => 'status', 'label' => 'Status', 'type' => 'select',
                     'options' => ['active' => 'Active', 'completed' => 'Completed',
                                   'ongoing' => 'Ongoing', 'inactive' => 'Hidden']],
                ]],
            ],

            'team' => [
                ['name' => 'members', 'label' => 'Team members', 'type' => 'repeat', 'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                    ['name' => 'role', 'label' => 'Role', 'type' => 'text'],
                    ['name' => 'category', 'label' => 'Group', 'type' => 'text',
                     'help' => 'Board, Staff, Volunteers — members are grouped by this.'],
                    ['name' => 'image', 'label' => 'Portrait', 'type' => 'image'],
                ]],
            ],

            'blog' => [
                ['name' => 'posts', 'label' => 'Posts', 'type' => 'repeat', 'fields' => [
                    ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['name' => 'slug', 'label' => 'URL slug', 'type' => 'text'],
                    ['name' => 'author', 'label' => 'Author', 'type' => 'text'],
                    ['name' => 'publish_date', 'label' => 'Published', 'type' => 'date'],
                    ['name' => 'featured_image', 'label' => 'Cover image', 'type' => 'image'],
                    ['name' => 'excerpt', 'label' => 'Excerpt', 'type' => 'textarea'],
                    ['name' => 'description', 'label' => 'Body', 'type' => 'textarea'],
                    ['name' => 'published', 'label' => 'Published', 'type' => 'checkbox'],
                ]],
            ],

            'events' => [
                ['name' => 'items', 'label' => 'Events', 'type' => 'repeat', 'fields' => [
                    ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                    ['name' => 'date', 'label' => 'Date', 'type' => 'date'],
                    ['name' => 'time', 'label' => 'Time', 'type' => 'text'],
                    ['name' => 'location', 'label' => 'Venue', 'type' => 'text'],
                    ['name' => 'city', 'label' => 'City', 'type' => 'text'],
                    ['name' => 'category', 'label' => 'Category', 'type' => 'text'],
                    ['name' => 'image_url', 'label' => 'Image', 'type' => 'image'],
                ]],
            ],

            'donate' => [
                ['name' => 'title', 'label' => 'Heading', 'type' => 'text'],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['name' => 'methods', 'label' => 'Ways to give', 'type' => 'repeat', 'fields' => [
                    ['name' => 'name', 'label' => 'Method', 'type' => 'text'],
                    ['name' => 'account_name', 'label' => 'Account name', 'type' => 'text'],
                    ['name' => 'account_number', 'label' => 'Account number', 'type' => 'text'],
                    ['name' => 'bank', 'label' => 'Bank', 'type' => 'text'],
                    ['name' => 'branch', 'label' => 'Branch', 'type' => 'text'],
                    ['name' => 'instructions', 'label' => 'How to pay', 'type' => 'textarea'],
                ]],
            ],

            'theme' => [
                ['name' => 'primary_color', 'label' => 'Primary colour', 'type' => 'color'],
                ['name' => 'secondary_color', 'label' => 'Secondary colour', 'type' => 'color'],
                ['name' => 'tertiary_color', 'label' => 'Tertiary colour', 'type' => 'color'],
            ],

            'chatbot' => [
                ['name' => 'enabled', 'label' => 'Show the chatbot', 'type' => 'checkbox'],
                ['name' => 'greeting', 'label' => 'Greeting', 'type' => 'textarea'],
                ['name' => 'model', 'label' => 'Model', 'type' => 'text'],
            ],

            'gallery' => [],

            default => [],
        };
    }

    /** Sections whose rows live in a table rather than the JSON. */
    public static function isManaged(string $section): bool
    {
        return $section === 'gallery';
    }

    public static function hasSchema(string $section): bool
    {
        return self::for($section) !== [];
    }
}
