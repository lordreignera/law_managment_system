@extends('layouts.admin')
@section('title', 'Add Payroll Employee')
@section('page-title', 'Add Payroll Employee')
@section('content')<section class="kfms-panel"><div class="kfms-panel-header"><div><h2>Add Payroll Employee</h2><span>Create the protected payroll profile and first salary version.</span></div><a class="kfms-link-btn" href="{{ route('payroll.employees.index') }}">Back to Employees</a></div><form class="kfms-form" method="POST" action="{{ route('payroll.employees.store') }}">@include('modules.payroll.employees._form')</form></section>@endsection
