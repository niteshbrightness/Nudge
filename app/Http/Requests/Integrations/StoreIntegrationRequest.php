<?php

namespace App\Http\Requests\Integrations;

use App\Services\IntegrationManager;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $service = $this->route('service')
            ?? $this->route('integration')?->service;

        if (! $service) {
            return [];
        }

        /** @var IntegrationManager $manager */
        $manager = app(IntegrationManager::class);
        $fields = $manager->get($service)::credentialFields();

        $isUpdate = $this->route('integration') !== null;

        $rules = [];
        foreach ($fields as $field) {
            $fieldRules = [];

            if ($field['required'] && ! ($isUpdate && $field['type'] === 'password')) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            $fieldRules[] = 'string';

            if ($field['type'] === 'url') {
                $fieldRules[] = 'url';
            }

            $rules[$field['name']] = $fieldRules;
        }

        return $rules;
    }
}
