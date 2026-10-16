@php
    $fieldKey = $field;
    $options = collect($users)->map(function ($user) {
        $roleLabel = $user->roles->pluck('name')->filter()->join(' / ') ?: 'Staff member';

        return [
            'value' => $user->id,
            'label' => $user->name,
            'detail' => $roleLabel,
            'search' => $user->name.' '.$roleLabel,
        ];
    })->all();
@endphp

@include('partials.check-dropdown', [
    'id' => 'matter-'.$fieldKey.'-picker',
    'name' => $name,
    'field' => $fieldKey,
    'label' => $label,
    'placeholder' => $placeholder,
    'options' => $options,
    'selected' => $selected ?? [],
    'selectAllLabel' => 'Select all '.strtolower($label),
    'hint' => 'Select one or more people. Their system role is shown below each name.',
])
