<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'hero' => ['required', 'array'],
            'hero.title' => ['required', 'string', 'max:200'],
            'hero.subtitle' => ['required', 'string', 'max:500'],
            'hero.image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'hero.image_alt' => ['nullable', 'string', 'max:300'],
            'hero.video_url' => ['nullable', 'url:http,https', 'max:2048'],
            'hero.primary_cta' => ['required', 'array'],
            'hero.primary_cta.label' => ['required', 'string', 'max:80'],
            'hero.primary_cta.href' => ['required', 'string', 'max:500', 'regex:/^\/(?!\/)/'],
            'hero.secondary_cta' => ['required', 'array'],
            'hero.secondary_cta.label' => ['required', 'string', 'max:80'],
            'hero.secondary_cta.href' => ['required', 'string', 'max:500', 'regex:/^\/(?!\/)/'],
            'how_it_works' => ['present', 'array', 'max:8'],
            'how_it_works.*.title' => ['required', 'string', 'max:100'],
            'how_it_works.*.text' => ['required', 'string', 'max:500'],
            'how_it_works.*.icon' => ['required', 'string', 'max:50'],
            'settings' => ['required', 'array'],
            'settings.site_name' => ['required', 'string', 'max:150'],
            'settings.contact_email' => ['nullable', 'email', 'max:255'],
            'settings.social' => ['present', 'array', 'max:10'],
            'settings.social.*.label' => ['required', 'string', 'max:80'],
            'settings.social.*.url' => ['required', 'url:http,https', 'max:2048'],
            'settings.footer_text' => ['nullable', 'string', 'max:500'],
            'featured_content_ids' => ['present', 'array', 'max:12'],
            'featured_content_ids.*' => ['integer', 'distinct', 'exists:contents,id'],
        ];
    }
}
