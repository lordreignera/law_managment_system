@extends('layouts.admin')
@section('title', 'Add Payroll Component')
@section('page-title', 'Add Payroll Component')
@section('content')
<section class="kfms-panel"><div class="kfms-panel-header"><div><h2>Add Payroll Component</h2><span>Configure an earning, deduction, tax, or employer contribution.</span></div><a class="kfms-link-btn" href="{{ route('payroll.components.index') }}">Back to Components</a></div><form class="kfms-form" method="POST" action="{{ route('payroll.components.store') }}">@include('modules.payroll.components._form')</form></section>
@endsection
