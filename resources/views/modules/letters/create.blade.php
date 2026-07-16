@extends('layouts.admin')

@section('title', 'Create Letter')
@section('page-title', 'Create Letter')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Create Letter / Opinion</h2>
                <span>Generate branded correspondence with reference number and signature.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('letters.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Letters
            </a>
        </div>

        @include('modules.letters.partials.workflow', ['letter' => $letter])

        @include('modules.letters.partials.form', [
            'action' => route('letters.store'),
            'method' => null,
        ])
    </section>
@endsection
