{{--
    The analog + digital clock from
    `packages/dashboard/src/components/Clock.jsx`.

    The CSS is carried over unchanged in `public/css/widgets.css` — every hand
    length and transform origin in it was arrived at by eye, and tidying them
    while porting is how a clock stops looking like one. The marks and hour
    digits are generated in JS rather than written out, because the original
    positions 48 marks with 48 `nth-child` rules.
--}}

<div class="clock-wrap">
    {{-- `data-timezone` carries the app's timezone to the clock so it reads
         Dar es Salaam time regardless of where the browser sits. --}}
    <div id="clock" data-clock data-timezone="{{ config('app.timezone') }}">
        <div class="frame-face"></div>
        <ul class="minute-marks" data-clock-marks></ul>

        <div class="digital-wrap" data-clock-digital>
            <span>--</span><span>--</span><span>--</span>
        </div>

        <ul class="digits" data-clock-digits></ul>
        <div class="hours-hand" data-clock-hours></div>
        <div class="minutes-hand" data-clock-minutes></div>
        <div class="seconds-hand" data-clock-seconds></div>
    </div>
</div>
