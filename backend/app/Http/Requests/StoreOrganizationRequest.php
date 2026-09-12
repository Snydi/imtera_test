<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => [
                'bail',
                'required',
                'string',
                'max:2048',
                'url:http,https',
                Rule::unique('organizations', 'url')->where('user_id', $this->user()->id),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $parts = parse_url($value);
                    $host = strtolower($parts['host'] ?? '');
                    $path = $parts['path'] ?? '';
                    $allowedHost = in_array($host, config('services.yandex_maps.allowed_hosts'), true);
                    $organizationPath = preg_match('~^/maps/org/(?:[^/]+/)?[1-9][0-9]*(?:/.*)?$~', $path);
                    $shortPath = preg_match('~^/maps/-/[a-zA-Z0-9_-]+/?$~', $path);

                    if (! $allowedHost || (! $organizationPath && ! $shortPath)
                        || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
                        $fail('Укажите ссылку на карточку организации в Яндекс.Картах.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Введите название компании.',
            'name.string' => 'Название компании должно быть строкой.',
            'name.max' => 'Название компании не должно превышать 255 символов.',
            'url.unique' => 'Организация с такой ссылкой уже добавлена.',
            'url.required' => 'Вставьте ссылку на организацию.',
            'url.string' => 'Ссылка должна быть строкой.',
            'url.max' => 'Ссылка не должна превышать 2048 символов.',
            'url.url' => 'Укажите корректную ссылку с http:// или https://.',
        ];
    }
}
