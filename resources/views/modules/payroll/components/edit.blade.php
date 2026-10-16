@extends('layouts.admin')
@section('title', 'Edit Payroll Component')
@section('page-title', 'Edit Payroll Component')
@section('content')
<section class="kfms-panel"><div class="kfms-panel-header"><div><h2>Edit Payroll Component</h2><span>{{ $component->code }} · {{ $component->name }}</span></div><a class="kfms-link-btn" href="{{ route('payroll.components.index') }}">Back to Components</a></div><form class="kfms-form" method="POST" action="{{ route('payroll.components.update', $component) }}">@include('modules.payroll.components._form')</form></section>
@endsection
