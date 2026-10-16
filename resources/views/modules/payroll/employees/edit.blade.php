@extends('layouts.admin')
@section('title', 'Edit Payroll Employee')
@section('page-title', 'Edit Payroll Employee')
@section('content')<section class="kfms-panel"><div class="kfms-panel-header"><div><h2>Edit Payroll Employee</h2><span>{{ $employee?->name }} · protected payroll details</span></div><a class="kfms-link-btn" href="{{ route('payroll.employees.show', $profile) }}">Back to Profile</a></div><form class="kfms-form" method="POST" action="{{ route('payroll.employees.update', $profile) }}">@include('modules.payroll.employees._form')</form></section>@endsection
