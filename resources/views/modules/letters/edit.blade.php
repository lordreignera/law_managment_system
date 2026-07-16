@extends('layouts.admin')

@section('title', 'Edit Letter')
@section('page-title', 'Edit Letter')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Edit Letter</h2>
                <span>{{ $letter->reference_no }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('letters.show', $letter) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Letter
            </a>
        </div>

        @include('modules.letters.partials.workflow', ['letter' => $letter])

        @include('modules.letters.partials.form', [
            'action' => route('letters.update', $letter),
            'method' => 'PUT',
        ])
    </section>
@endsection
