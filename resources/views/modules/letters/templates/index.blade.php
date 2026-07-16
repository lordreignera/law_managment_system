@extends('layouts.admin')

@section('title', 'Letter Templates')
@section('page-title', 'Letter Templates')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Template Library</h2>
                <span>Reusable wording for letters, opinions, notices, and engagement documents.</span>
            </div>
            <div class="kfms-toolbar-actions">
                <a class="kfms-link-btn" href="{{ route('letters.index') }}">
                    <i class="mdi mdi-arrow-left"></i>
                    Back to Letters
                </a>
                <button class="kfms-btn" type="button" data-bs-toggle="modal" data-bs-target="#create-template-modal">
                    <i class="mdi mdi-plus"></i>
                    Add Template
                </button>
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="kfms-alert kfms-alert-danger">{{ $errors->first() }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('letters.templates.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search template, code, or subject">
            </label>
            <label>
                <span>Category</span>
                <select name="category">
                    <option value="">All Categories</option>
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('letters.templates.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr>
                            <td><strong>{{ $template->code }}</strong></td>
                            <td>{{ $template->name }}</td>
                            <td>{{ $template->categoryLabel() }}</td>
                            <td>{{ $template->subject ?: '-' }}</td>
                            <td><span class="kfms-status kfms-status-{{ $template->is_active ? 'active' : 'rejected' }}">{{ $template->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <div class="kfms-table-actions">
                                    <button type="button" data-bs-toggle="modal" data-bs-target="#edit-template-{{ $template->id }}">Edit</button>
                                    <form method="POST" action="{{ route('letters.templates.destroy', $template) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="is-danger" type="submit" onclick="return confirm('Delete this template?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        @push('modals')
                            @include('modules.letters.templates.modal', [
                                'modalId' => 'edit-template-'.$template->id,
                                'template' => $template,
                                'action' => route('letters.templates.update', $template),
                                'method' => 'PUT',
                                'categories' => $categories,
                                'letterheads' => $letterheads,
                            ])
                        @endpush
                    @empty
                        <tr>
                            <td colspan="6" class="kfms-empty">No templates found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $templates->links() }}
    </section>

    @push('modals')
        @include('modules.letters.templates.modal', [
            'modalId' => 'create-template-modal',
            'template' => new \App\Models\LetterTemplate(['is_active' => true]),
            'action' => route('letters.templates.store'),
            'method' => null,
            'categories' => $categories,
            'letterheads' => $letterheads,
        ])
    @endpush
@endsection
