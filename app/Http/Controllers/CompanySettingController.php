<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CompanySettingController extends Controller
{
    public function edit()
    {
        return view('modules.settings.company', [
            'setting' => CompanySetting::current(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:191'],
            'short_name' => ['required', 'string', 'max:40'],
            'initials' => ['required', 'string', 'max:8'],
            'logo' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/svg+xml', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'tagline' => ['nullable', 'string', 'max:191'],
            'login_heading' => ['nullable', 'string', 'max:191'],
            'login_subheading' => ['nullable', 'string', 'max:1000'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
        ]);

        unset($data['logo'], $data['remove_logo']);

        $setting = CompanySetting::current();

        if ($request->boolean('remove_logo')) {
            $this->deleteLogo($setting->logo_path);
            $data['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            $this->deleteLogo($setting->logo_path);
            $data['logo_path'] = $this->storeLogo($request->file('logo'), $data['short_name']);
        }

        $setting->update($data);

        return back()->with('status', 'Company branding updated.');
    }

    private function storeLogo(UploadedFile $logo, string $shortName): string
    {
        $directory = public_path('uploads/company-logos');
        File::ensureDirectoryExists($directory);

        $name = Str::slug($shortName) ?: 'company';
        $extension = strtolower($logo->getClientOriginalExtension() ?: $logo->extension());
        $filename = $name.'-'.now()->format('YmdHis').'-'.Str::random(6).'.'.$extension;

        $logo->move($directory, $filename);

        return 'uploads/company-logos/'.$filename;
    }

    private function deleteLogo(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (in_array($path, CompanySetting::defaultLogoPaths(), true)) {
            return;
        }

        $file = public_path($path);
        $logoDirectory = realpath(public_path('uploads/company-logos'));
        $fileDirectory = realpath(dirname($file));

        if ($logoDirectory && $fileDirectory && str_starts_with($fileDirectory, $logoDirectory)) {
            File::delete($file);
        }
    }
}
