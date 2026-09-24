@extends('layouts.admin')

@section('title', 'Reschedule Leave')
@section('page-title', 'Reschedule Leave')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Reschedule {{ $leave->leave_no }}</h2>
                <span>Update the dates or details and send the rejected request through approval again.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('leave.show', $leave) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Request
            </a>
        </div>

        @if ($errors->any())
            <div class="kfms-alert kfms-alert-danger">Please correct the highlighted fields and try again.</div>
        @endif

        <form class="kfms-form" method="POST" action="{{ route('leave.reschedule.update', $leave) }}" enctype="multipart/form-data">
            @csrf
            @method('PATCH')

            <div class="kfms-form-grid">
                <label>
                    <span>Leave Type</span>
                    <select name="leave_type_id" required>
                        @foreach ($leaveTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) old('leave_type_id', $leave->leave_type_id) === (string) $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('leave_type_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Start Date</span>
                    <input type="date" name="start_date" value="{{ old('start_date', $leave->start_date?->format('Y-m-d')) }}" required>
                    @error('start_date') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>End Date</span>
                    <input type="date" name="end_date" value="{{ old('end_date', $leave->end_date?->format('Y-m-d')) }}" required>
                    @error('end_date') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Reason</span>
                    <textarea name="reason" rows="3">{{ old('reason', $leave->reason) }}</textarea>
                    @error('reason') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Additional Supporting Document (optional)</span>
                    <input type="file" name="attachment">
                    @error('attachment') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('leave.show', $leave) }}">Cancel</a>
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-send"></i>
                    Resubmit for Approval
                </button>
            </div>
        </form>
    </section>
@endsection
