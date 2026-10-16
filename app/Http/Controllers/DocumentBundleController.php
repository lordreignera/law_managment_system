<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\DocumentBundle;
use App\Models\Matter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use ZipArchive;

class DocumentBundleController extends Controller
{
    public function index(Matter $matter)
    {
        $this->ensureVisible($matter);

        return view('modules.document-bundles.index', [
            'matter' => $matter,
            'bundles' => $matter->documentBundles()->with('creator')->latest()->get(),
        ]);
    }

    public function create(Matter $matter)
    {
        $this->ensureVisible($matter);

        return view('modules.document-bundles.create', [
            'matter' => $matter,
            'attachments' => $this->availableAttachments($matter),
            'bundleTypes' => ['pleadings' => 'Pleadings', 'evidence' => 'Evidence', 'court_record' => 'Court Record', 'other' => 'Other'],
        ]);
    }

    public function store(Request $request, Matter $matter)
    {
        $this->ensureVisible($matter);
        $data = $this->validated($request, $matter);

        $bundle = DB::transaction(function () use ($data, $matter, $request) {
            $bundle = $matter->documentBundles()->create([
                'created_by' => $request->user()->id,
                'name' => $data['name'],
                'bundle_type' => $data['bundle_type'],
                'status' => $data['status'] ?? 'draft',
                'description' => $data['description'] ?? null,
                'finalized_at' => ($data['status'] ?? 'draft') === 'finalized' ? now() : null,
            ]);

            $this->syncItems($bundle, $data['attachment_ids'] ?? [], $data['labels'] ?? [], $data['sort_orders'] ?? []);

            return $bundle;
        });

        return redirect()->route('matters.bundles.show', [$matter, $bundle])->with('status', 'Document bundle created.');
    }

    public function show(Matter $matter, DocumentBundle $bundle)
    {
        $this->ensureBundleVisible($matter, $bundle);

        return view('modules.document-bundles.show', [
            'matter' => $matter,
            'bundle' => $bundle->load(['creator', 'items.attachment']),
        ]);
    }

    public function edit(Matter $matter, DocumentBundle $bundle)
    {
        $this->ensureBundleVisible($matter, $bundle);

        return view('modules.document-bundles.edit', [
            'matter' => $matter,
            'bundle' => $bundle->load('items'),
            'attachments' => $this->availableAttachments($matter),
            'bundleTypes' => ['pleadings' => 'Pleadings', 'evidence' => 'Evidence', 'court_record' => 'Court Record', 'other' => 'Other'],
        ]);
    }

    public function update(Request $request, Matter $matter, DocumentBundle $bundle)
    {
        $this->ensureBundleVisible($matter, $bundle);
        $data = $this->validated($request, $matter, true);

        DB::transaction(function () use ($bundle, $data) {
            $bundle->update([
                'name' => $data['name'],
                'bundle_type' => $data['bundle_type'],
                'status' => $data['status'],
                'description' => $data['description'] ?? null,
                'finalized_at' => $data['status'] === 'finalized' ? ($bundle->finalized_at ?: now()) : null,
            ]);
            if (array_key_exists('attachment_ids', $data)) {
                $bundle->items()->get()->each->delete();
                $this->syncItems($bundle, $data['attachment_ids'] ?? [], $data['labels'] ?? [], $data['sort_orders'] ?? []);
            }
        });

        return redirect()->route('matters.bundles.show', [$matter, $bundle])->with('status', 'Document bundle updated.');
    }

    public function download(Matter $matter, DocumentBundle $bundle)
    {
        $this->ensureBundleVisible($matter, $bundle);
        $bundle->load('items.attachment');

        abort_unless(class_exists(ZipArchive::class), 503, 'ZIP downloads are not available on this server.');

        $temporaryPath = tempnam(sys_get_temp_dir(), 'kfms-bundle-');
        $zip = new ZipArchive;
        abort_unless($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500);

        foreach ($bundle->items as $index => $item) {
            $attachment = $item->attachment;
            if (! $attachment) {
                continue;
            }
            $disk = Storage::disk($attachment->disk ?: config('filesystems.default'));
            if ($disk->exists($attachment->path)) {
                $name = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT).'_'.($item->label ?: $attachment->original_name);
                $zip->addFromString($name, $disk->get($attachment->path));
            }
        }

        $zip->close();

        return response()->download($temporaryPath, str($bundle->name)->slug().'.zip')->deleteFileAfterSend(true);
    }

    private function validated(Request $request, Matter $matter, bool $updating = false): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'bundle_type' => ['required', Rule::in(['pleadings', 'evidence', 'court_record', 'other'])],
            'status' => ['required', Rule::in(array_keys(DocumentBundle::STATUSES))],
            'description' => ['nullable', 'string', 'max:2000'],
            'attachment_ids' => [$updating ? 'sometimes' : 'required', 'array', 'min:1'],
            'attachment_ids.*' => ['integer', 'distinct', 'exists:attachments,id'],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['nullable', 'string', 'max:191'],
            'sort_orders' => ['nullable', 'array'],
            'sort_orders.*' => ['integer', 'min:1', 'max:9999'],
        ]);

        $ids = array_values(array_unique($data['attachment_ids'] ?? []));
        if ($ids !== []) {
            $valid = $this->availableAttachments($matter)->whereIn('id', $ids)->pluck('id')->all();
            if (count($valid) !== count($ids)) {
                abort(422, 'Select only current documents attached to this matter.');
            }
        }

        return $data;
    }

    private function availableAttachments(Matter $matter)
    {
        return Attachment::query()
            ->where('attachable_type', $matter->getMorphClass())
            ->where('attachable_id', $matter->id)
            ->where('is_current', true)
            ->orderBy('category')
            ->orderBy('title')
            ->orderBy('original_name')
            ->get();
    }

    private function syncItems(DocumentBundle $bundle, array $attachmentIds, array $labels, array $sortOrders = []): void
    {
        $items = collect(array_values($attachmentIds))
            ->values()
            ->map(fn ($attachmentId, $index) => [
                'id' => $attachmentId,
                'requested_order' => (int) ($sortOrders[$attachmentId] ?? $index + 1),
                'index' => $index,
            ])
            ->all();
        usort($items, fn (array $left, array $right) => ($left['requested_order'] <=> $right['requested_order']) ?: ($left['index'] <=> $right['index']));

        foreach ($items as $index => $item) {
            $attachmentId = $item['id'];
            $bundle->items()->create([
                'attachment_id' => $attachmentId,
                'sort_order' => $index + 1,
                'label' => $labels[$attachmentId] ?? null,
            ]);
        }
    }

    private function ensureVisible(Matter $matter): void
    {
        abort_unless(Matter::visibleTo(request()->user())->whereKey($matter->id)->exists(), 404);
    }

    private function ensureBundleVisible(Matter $matter, DocumentBundle $bundle): void
    {
        $this->ensureVisible($matter);
        abort_unless((int) $bundle->matter_id === (int) $matter->id, 404);
    }
}
