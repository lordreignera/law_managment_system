@extends('layouts.admin')

@section('title', 'Assign Recovery')
@section('page-title', 'Assign Recovery')

@section('content')
    <section class="kfms-panel kfms-recovery-assignment-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Assign Recovery Officer</h2>
                <span>{{ $account->debtor_name }} - {{ $account->client?->name ?: 'No client' }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('recoveries.show', $account) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back
            </a>
        </div>

        @if ($errors->any())
            <div class="kfms-alert kfms-alert-danger">
                Please correct the highlighted fields and try again.
            </div>
        @endif

        <div class="kfms-access-user-summary">
            <div>
                <span class="kfms-card-label">Current Officer</span>
                <strong>{{ $account->assignee?->name ?: 'Unassigned' }}</strong>
            </div>
            <div>
                <span class="kfms-card-label">Current Branch</span>
                <strong>{{ $account->branch?->name ?: 'Firm-wide' }}</strong>
            </div>
            <div>
                <span class="kfms-card-label">Assigned By</span>
                <strong>{{ $account->assigner?->name ?: '-' }}</strong>
            </div>
            <div>
                <span class="kfms-card-label">Assigned On</span>
                <strong>{{ $account->assigned_at?->format('d M Y') ?: '-' }}</strong>
            </div>
        </div>

        <form class="kfms-form kfms-access-user-form" method="POST" action="{{ route('recoveries.assignment.update', $account) }}">
            @csrf
            @method('PATCH')
            <div class="kfms-form-grid">
                <label>
                    <span>Recovery Officer</span>
                    <select name="assigned_to">
                        <option value="">Unassigned</option>
                        @foreach ($officers as $officer)
                            <option value="{{ $officer->id }}" @selected(old('assigned_to', $account->assigned_to) == $officer->id)>
                                {{ $officer->name }}@if ($officer->branch) - {{ $officer->branch->name }} @endif
                            </option>
                        @endforeach
                    </select>
                    @error('assigned_to') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Branch</span>
                    <select name="branch_id">
                        <option value="">Auto / firm-wide</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id', $account->branch_id) == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('recoveries.show', $account) }}">Cancel</a>
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-account-switch-outline"></i>
                    Save Assignment
                </button>
            </div>
        </form>
    </section>
@endsection
