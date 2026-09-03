{{--
    Which sections a page is made of.

    **Every template includes this file.** That is the guarantee that changing a
    site's template changes only its appearance: the content of /about is
    decided here, once, not five times.

    The home page shows a trimmed version of the long lists (`$limit`), because
    a home page that dumps forty gallery images is a home page nobody scrolls.
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
        @include('templates.sections.hero')
        @include('templates.sections.about')
        @include('templates.sections.projects')
        @include('templates.sections.gallery', ['limit' => 8])
        @include('templates.sections.events', ['limit' => 3])
        @include('templates.sections.blog', ['limit' => 3])
        @include('templates.sections.team')
        @include('templates.sections.donate')
@endswitch
