@extends('layouts.app')
@section('title', __('Access'))

@section('content')
    <h1>{{ __('Module access') }}</h1>
    <p class="sub">{{ __('Which roles may enter which modules in :org.', ['org' => $organization->name]) }}</p>

    @include('organization._tabs')

    @if (empty($modules))
        <div class="card">
            <p class="dim">{{ __('No modules are installed yet.') }}</p>
            <p class="dim small"><code>php artisan module:init Crm</code></p>
        </div>
    @else
        <form method="POST" action="{{ route('organization.access.update') }}" class="card table-wrap">
            @csrf
            @method('PUT')

            <p class="dim small">
                {{ __('Administration always keeps every module — a matrix that could lock out every admin would leave nobody able to undo it. Within a module, what a role may actually do is fixed by its rank.') }}
            </p>

            <table>
                <tr>
                    <th>{{ __('Module') }}</th>
                    @foreach (\App\Support\Access::ROLES as $role)
                        <th>
                            {{ \App\Support\Access::roleLabel($role) }}
                            <div class="dim small" style="font-weight:400;text-transform:none;letter-spacing:0">
                                {{ \App\Support\Access::ROLE_HINTS[$role] }}
                            </div>
                        </th>
                    @endforeach
                </tr>

                @foreach ($modules as $slug => $module)
                    <tr>
                        <td>
                            <strong>{{ $module['label'] }}</strong>
                            <div class="dim small">{{ $module['description'] ?? '' }}</div>
                            @if (! $organization->isGranted($slug))
                                <span class="badge offline">{{ __('not granted by the system') }}</span>
                            @elseif (! $organization->planIncludes($slug))
                                <span class="badge moderate">{{ __('not in :plan', ['plan' => $organization->planLabel()]) }}</span>
                            @endif
                        </td>

                        @foreach (\App\Support\Access::ROLES as $role)
                            <td>
                                <input type="checkbox"
                                       name="access[{{ $role }}][{{ $slug }}]"
                                       value="1"
                                       style="width:auto"
                                       @checked($matrix[$role][$slug])
                                       @disabled(! $canManage || $role === 'admin')>
                            </td>
                        @endforeach
                    </tr>

                    {{-- Tabs, a row each. Leaving one ticked while its module is
                         unticked is how somebody gets exactly one part of a
                         module — the storekeeper who needs Stock and nothing
                         else in Inventory. --}}
                    @foreach ($sections[$slug] ?? [] as $key => $label)
                        <tr class="section-row">
                            <td class="section-name">↳ {{ $label }}</td>
                            @foreach (\App\Support\Access::ROLES as $role)
                                <td>
                                    <input type="checkbox"
                                           name="sections[{{ $role }}][{{ $slug }}][{{ str_replace('.', '__', $key) }}]"
                                           value="1"
                                           style="width:auto"
                                           @checked($sectionMatrix[$role][$slug][$key] ?? false)
                                           @disabled(! $canManage || $role === 'admin')>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </table>

            @if ($canManage)
                <button class="btn" type="submit" style="margin-top:12px">{{ __('Save access') }}</button>
            @endif
        </form>
    @endif

    <div class="card">
        <h2 style="margin-top:0">{{ __('What each role may do') }}</h2>
        <div class="table-wrap">
            <table>
                <tr>
                    <th>{{ __('Role') }}</th>
                    @foreach (\App\Support\Access::ACTIONS as $action)
                        <th>{{ \App\Support\Access::ACTION_LABELS[$action] }}</th>
                    @endforeach
                </tr>
                @foreach (\App\Support\Access::ROLES as $role)
                    <tr>
                        <td>{{ \App\Support\Access::roleLabel($role) }}</td>
                        @foreach (\App\Support\Access::ACTIONS as $action)
                            <td>{!! \App\Support\Access::can($role, $action) ? '<span class="badge resolved">✓</span>' : '<span class="dim">—</span>' !!}</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
@endsection
