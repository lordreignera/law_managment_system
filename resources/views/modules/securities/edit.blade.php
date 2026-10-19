@extends('layouts.admin')

@section('title', 'Edit Security')
@section('page-title', 'Securities')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Edit Security</h2>
                <span>{{ $title->reference_no }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('securities.show', $title) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Details
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('securities.update', $title) }}" enctype="multipart/form-data">
            @method('PUT')
            @include('modules.securities.partials.form', ['buttonText' => 'Update Security'])
        </form>
    </section>
@endsection
