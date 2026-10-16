@props(['value' => null, 'required' => true])
<select name="job_title" {{ $attributes }} @required($required)>
    <option value="">Select job title</option>
    @foreach (\App\Models\JobTitle::where('is_active', true)->when($value, fn ($query) => $query->orWhere('name', $value))->orderBy('sort_order')->orderBy('name')->get() as $jobTitle)
        <option value="{{ $jobTitle->name }}" @selected(old('job_title', $value) === $jobTitle->name)>{{ $jobTitle->name }}</option>
    @endforeach
</select>
