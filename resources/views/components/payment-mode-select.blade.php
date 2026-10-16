<select name="payment_method">
    <option value="">Select payment method</option>
    @foreach (\App\Models\PaymentMode::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get() as $mode)
        <option value="{{ $mode->name }}" @selected(old('payment_method') === $mode->name)>{{ $mode->name }}</option>
    @endforeach
</select>
