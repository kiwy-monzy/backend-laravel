{{--
    The page composition from HomePage.tsx, section for section.

    It differs from the shared `templates/_page.blade.php` only in the hero —
    template 0 uses its own — and in ordering: the original put Volunteer
    between Gallery and Events, and Donate last.
--}}
@switch($page)
    @case('about')
        @include('templates.sections.about')
        @include('templates.sections.team')
        @break

    @case('projects')
        @include('templates.sections.projects')
        @break

    @case('gallery')
        @include('templates.sections.gallery')
        @break

    @case('blog')
        @include('templates.sections.blog')
        @break

    @case('events')
        @include('templates.sections.events')
        @break

    @case('team')
        @include('templates.sections.team')
        @break

    @case('donate')
        @include('templates.sections.donate')
        @break

    @case('post')
        @include('templates.sections.post')
        @break

    @case('event')
        @include('templates.sections.event')
        @break

    @case('contact')
        @include('templates.sections.contact')
        @break

    @default
        @include('templates.template0.hero')
        @include('templates.sections.about')
        @include('templates.sections.projects')
        @include('templates.sections.team')
        @include('templates.sections.gallery', ['limit' => 8])
        @include('templates.sections.events', ['limit' => 3])
        @include('templates.sections.blog', ['limit' => 3])
        @include('templates.sections.donate')
@endswitch
