@extends('layouts.admin')

@section('title', 'New Leave Request')
@section('page-title', 'New Leave Request')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Request Leave</h2>
                <span>Submit your leave request for approval.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('leave.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Leave
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('leave.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="kfms-form-grid">
                <label>
                    <span>Leave Number</span>
                    <input type="text" value="{{ $leaveNumber }}" readonly disabled>
                </label>

                <label>
                    <span>Leave Type</span>
                    <select name="leave_type_id" required>
                        <option value="">Select type</option>
                        @foreach ($leaveTypes as $type)
                            <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('leave_type_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Start Date</span>
                    <input type="date" name="start_date" value="{{ old('start_date') }}" required>
                    @error('start_date') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>End Date</span>
                    <input type="date" name="end_date" value="{{ old('end_date') }}" required>
                    @error('end_date') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Reason</span>
                    <textarea name="reason" rows="3" placeholder="Optional notes for the approver">{{ old('reason') }}</textarea>
                    @error('reason') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Supporting Document (optional)</span>
                    <input type="file" name="attachment">
                    @error('attachment') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-send"></i>
                    Submit Request
                </button>
            </div>
        </form>
    </section>
@endsection
