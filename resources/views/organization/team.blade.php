@extends('layouts.app')
@section('title', __('Team'))

@section('content')
    <h1>{{ __('Team') }}</h1>
    <p class="sub">{{ $organization->name }} · {{ trans_choice(':count member|:count members', $members->count(), ['count' => $members->count()]) }}</p>

    @include('organization._tabs')

    <div class="card table-wrap">
        <table>
            <tr>
                <th>{{ __('Person') }}</th>
                <th>{{ __('Job title') }}</th>
                <th>{{ __('Role') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Can') }}</th>
                <th>{{ __('Active') }}</th>
                <th></th>
            </tr>
            @foreach ($members as $member)
                <tr>
                    <td>
                        <div class="person-cell">
                            {{-- The portrait is uploaded here because this is
                                 the team, and the public site's team section
                                 renders these same seats. --}}
                            <form method="POST" action="{{ route('organization.team.avatar', $member) }}"
                                  enctype="multipart/form-data" class="avatar-form">
                                @csrf
                                <label class="avatar" title="{{ __('Change portrait') }}">
                                    @if ($member->photo_url)
                                        <img src="{{ $member->photo_url }}" alt="">
                                    @else
                                        <span>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($member->displayName() ?: '?', 0, 1)) }}</span>
                                    @endif
                                    <input type="file" name="avatar" accept="image/*" onchange="this.form.submit()">
                                </label>

                                {{-- Or pick a picture already in the
                                     organization's files. The row is hidden
                                     until asked for so the team list stays a
                                     list rather than a wall of inputs. --}}
                                @if ($canManage)
                                    <details class="avatar-choose">
                                        <summary class="dim small">{{ __('choose') }}</summary>
                                        <x-image-field name="photo_url" :value="$member->photo_url" />
                                        <button class="btn small" type="submit">{{ __('Use') }}</button>
                                    </details>
                                @endif
                            </form>
                            <div>
                                <strong>{{ $member->displayName() ?: '—' }}</strong>
                                @if ($member->user_id === auth()->id())<span class="chip">{{ __('you') }}</span>@endif
                                @unless ($member->user_id)<span class="chip">{{ __('no login') }}</span>@endunless
                                <div class="dim small">
                                    {{ $member->user?->email ?: $member->displayTitle() }}
                                    @if ($member->show_on_website)
                                        · <span class="dim">{{ __('on the site as :group', ['group' => $member->collectionLabel()]) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="small dim">{{ $member->job_title ?: '—' }}</td>
                    <td>
                        <form method="POST" action="{{ route('organization.team.update', $member) }}" class="inline-form">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="job_title" value="{{ $member->job_title }}">
                            <input type="hidden" name="employee_type" value="{{ $member->employee_type }}">
                            <input type="hidden" name="active" value="{{ $member->active ? 1 : 0 }}">
                            <select name="role" onchange="this.form.submit()" @disabled(! $canManage)>
                                @foreach (\App\Support\Access::ROLES as $role)
                                    <option value="{{ $role }}" @selected($member->role === $role)>
                                        {{ \App\Support\Access::roleLabel($role) }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('organization.team.update', $member) }}" class="inline-form">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="role" value="{{ $member->role }}">
                            <input type="hidden" name="job_title" value="{{ $member->job_title }}">
                            <input type="hidden" name="active" value="{{ $member->active ? 1 : 0 }}">
                            <select name="employee_type" onchange="this.form.submit()" @disabled(! $canManage)>
                                <option value="">{{ __('—') }}</option>
                                @foreach (\App\Support\Access::EMPLOYEE_TYPES as $key => $label)
                                    <option value="{{ $key }}" @selected($member->employee_type === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                    </td>
                    <td class="small dim">
                        @foreach (\App\Support\Access::ACTIONS as $action)
                            @if (\App\Support\Access::can($member->role, $action, $member->employee_type))
                                <span class="badge">{{ \App\Support\Access::ACTION_LABELS[$action] }}</span>
                            @endif
                        @endforeach
                    </td>
                    <td>{{ $member->active ? __('Yes') : __('No') }}</td>
                    <td class="right-align">
                        @if ($canManage && $member->user_id !== auth()->id())
                            <form method="POST" action="{{ route('organization.team.remove', $member) }}" class="inline-form"
                                  data-confirm="{{ __('Remove :name from the team?', ['name' => $member->user?->username]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn small danger" type="submit">{{ __('Remove') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

    @if ($canManage)
        <form method="POST" action="{{ route('organization.team.add') }}" class="card">
            @csrf
            <h2 style="margin-top:0">{{ __('Add a team member') }}</h2>
            <p class="dim small">
                {{ __('Creates their login and seats them in this organization. They can manage the modules their role allows, but not the public website until you promote them under Users.') }}
            </p>
            <p class="dim small">
                {{ __('Role is authority; type is the job. Shifts and contract activities are recorded against the type, and a Salesperson or Supervisor may also edit customer-facing records.') }}
            </p>

            <div class="row">
                <label>
                    <span>{{ __('Username') }}</span>
                    <input type="text" name="username" required maxlength="60">
                </label>
                <label>
                    <span>{{ __('Email') }}</span>
                    <input type="email" name="email" required>
                </label>
                <label>
                    <span>{{ __('Password') }}</span>
                    <input type="password" name="password" required minlength="8" autocomplete="new-password">
                </label>
            </div>

            <div class="row">
                <label>
                    <span>{{ __('Role') }}</span>
                    <select name="role" required>
                        @foreach (\App\Support\Access::ROLES as $role)
                            <option value="{{ $role }}" @selected($role === 'employee')>
                                {{ \App\Support\Access::roleLabel($role) }} — {{ \App\Support\Access::ROLE_HINTS[$role] }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>{{ __('Employee type') }}</span>
                    <select name="employee_type">
                        <option value="">{{ __('— not set —') }}</option>
                        @foreach (\App\Support\Access::EMPLOYEE_TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>{{ __('Job title') }}</span>
                    <input type="text" name="job_title" maxlength="120">
                </label>
                <label>
                    <span>{{ __('Website') }}</span>
                    <select name="website_id">
                        @foreach ($organization->websites as $w)
                            <option value="{{ $w->id }}">{{ $w->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <button class="btn" type="submit">{{ __('Add to team') }}</button>
        </form>
    @endif

    @if ($unseated->isNotEmpty())
        <div class="card">
            <h2 style="margin-top:0">{{ __('Not on the team yet') }}</h2>
            <p class="dim small">{{ __('These accounts belong to the organization but hold no seat, so they cannot open any module.') }}</p>
            <table>
                @foreach ($unseated as $u)
                    <tr><td>{{ $u->username }}</td><td class="dim small">{{ $u->email }}</td></tr>
                @endforeach
            </table>
        </div>
    @endif

    <x-image-picker />
@endsection
