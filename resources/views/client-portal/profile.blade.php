@extends('layouts.client')

@section('title', 'My Profile')

@section('content')
    <section class="kfms-panel kfms-profile-shell">
        <div class="kfms-panel-header">
            <div>
                <h1>My Profile</h1>
                <span>Update the profile details used in your client portal.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('client.dashboard') }}">
                <i class="mdi mdi-arrow-left" aria-hidden="true"></i>
                Back to Dashboard
            </a>
        </div>

        <div class="kfms-profile-summary">
            <img class="kfms-profile-avatar" src="{{ $profileUser->profile_photo_url }}" alt="{{ $profileUser->name }}">
            <div>
                <strong>{{ $profileUser->name }}</strong>
                <span>{{ $profileUser->email }}</span>
            </div>
        </div>

        <div class="kfms-profile-sections">
            <div class="kfms-profile-section">
                @livewire('profile.update-profile-information-form')
            </div>
        </div>
    </section>
@endsection
