@extends('layouts.app')
@section('title', __('System'))

@section('content')
    <h1>{{ __('System') }}</h1>
    <p class="sub">{{ __('Every organization and every account on this installation.') }}</p>

    <p class="nav" style="margin-bottom:16px">
        <a href="{{ route('system.index') }}" class="on">{{ __('Organizations') }}</a>
        <a href="{{ route('system.users') }}">{{ __('All users') }}</a>
    </p>

    <div class="grid c3">
        <div class="stat"><div class="n">{{ number_format($counts['organizations']) }}</div><div class="k">{{ __('Organizations') }}</div></div>
        <div class="stat"><div class="n">{{ number_format($counts['users']) }}</div><div class="k">{{ __('Users') }}</div></div>
        <div class="stat"><div class="n">{{ number_format($counts['websites']) }}</div><div class="k">{{ __('Websites') }}</div></div>
        <div class="stat"><div class="n">{{ number_format($counts['system_admins']) }}</div><div class="k">{{ __('System admins') }}</div></div>
        <div class="stat"><div class="n">{{ number_format($counts['owners']) }}</div><div class="k">{{ __('Organization owners') }}</div></div>
        <div class="stat"><div class="n">{{ number_format($counts['modules']) }}</div><div class="k">{{ __('Modules installed') }}</div></div>
    </div>

    <p style="margin-top:16px">
        <a class="btn" href="{{ route('system.organization.create') }}">{{ __('New organization') }}</a>
    </p>

    <div class="card table-wrap">
        <table>
            <tr>
                <th>{{ __('Organization') }}</th>
                <th>{{ __('Owner') }}</th>
                <th>{{ __('Plan') }}</th>
                <th class="right-align">{{ __('Team') }}</th>
                <th class="right-align">{{ __('Websites') }}</th>
                <th>{{ __('Status') }}</th>
            </tr>
            @foreach ($organizations as $org)
                <tr>
                    <td>
                        <a href="{{ route('system.organization', $org) }}">{{ $org->name }}</a>
                        <div class="dim small">{{ $org->slug }}</div>
                    </td>
                    <td class="small dim">{{ $org->owner?->username ?? '—' }}</td>
                    <td class="small">{{ $org->planLabel() }}</td>
                    <td class="right-align">{{ $org->members_count }}</td>
                    <td class="right-align">{{ $org->websites_count }}</td>
                    <td>
                        <span class="badge {{ $org->isActive() ? 'resolved' : 'critical' }}">
                            {{ $org->isActive() ? __('active') : __('lapsed') }}
                        </span>
                        @if ($org->onTrial())
                            <span class="dim small">{{ __(':n days left', ['n' => $org->trialDaysLeft()]) }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
