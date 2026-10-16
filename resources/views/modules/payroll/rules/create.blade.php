@extends('layouts.admin')
@section('title', 'Add Payroll Rule')
@section('page-title', 'Add Payroll Rule')
@section('content')<section class="kfms-panel"><div class="kfms-panel-header"><div><h2>Add Calculation Rule</h2><span>Keep statutory and custom rules versioned by effective date.</span></div><a class="kfms-link-btn" href="{{ route('payroll.rules.index') }}">Back to Rules</a></div><form class="kfms-form" method="POST" action="{{ route('payroll.rules.store') }}">@include('modules.payroll.rules._form')</form></section>@endsection
