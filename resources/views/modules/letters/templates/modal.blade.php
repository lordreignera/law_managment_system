<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content kfms-modal" method="POST" action="{{ $action }}">
            @csrf
            @if ($method)
                @method($method)
            @endif

            <div class="modal-header">
                <div>
                    <h5 class="modal-title">{{ $template->exists ? 'Edit Template' : 'Create Template' }}</h5>
                    <span class="kfms-muted">{{ $template->code ?: 'New reusable template' }}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="kfms-form-grid">
                    <label>
                        <span>Name <b>*</b></span>
                        <input type="text" name="name" value="{{ old('name', $template->name) }}" required>
                    </label>
                    <label>
                        <span>Code <b>*</b></span>
                        <input type="text" name="code" value="{{ old('code', $template->code) }}" required>
                    </label>
                    <label>
                        <span>Category <b>*</b></span>
                        <select name="category" required>
                            @foreach ($categories as $value => $label)
                                <option value="{{ $value }}" @selected(old('category', $template->category ?: 'general') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>Letterhead</span>
                        <select name="letterhead_id">
                            <option value="">Default</option>
                            @foreach ($letterheads as $letterhead)
                                <option value="{{ $letterhead->id }}" @selected((string) old('letterhead_id', $template->letterhead_id) === (string) $letterhead->id)>{{ $letterhead->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="kfms-span-2">
                        <span>Subject</span>
                        <input type="text" name="subject" value="{{ old('subject', $template->subject) }}">
                    </label>
                    <label class="kfms-span-2">
                        <span>Body <b>*</b></span>
                        <textarea name="body" rows="10" required>{{ old('body', $template->body) }}</textarea>
                    </label>
                    <label class="kfms-span-2">
                        <span>Description</span>
                        <textarea name="description" rows="3">{{ old('description', $template->description) }}</textarea>
                    </label>
                    <label>
                        <span>Sort Order</span>
                        <input type="number" name="sort_order" value="{{ old('sort_order', $template->sort_order ?: 0) }}" min="0">
                    </label>
                    <label class="kfms-checkbox-line">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active ?? true))>
                        <span>Active</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="kfms-btn" type="submit">Save Template</button>
            </div>
        </form>
    </div>
</div>
