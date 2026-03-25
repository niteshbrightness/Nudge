<?php

namespace App\Http\Requests\Clients;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), [
            'project_ids' => array_values(array_filter(
                (array) $this->input('project_ids', []),
                fn ($v) => $v !== '' && $v !== null
            )),
            'is_active' => filter_var($this->input('is_active', 'true'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{1,14}$/'],
            'timezone_id' => ['required', 'integer', 'exists:timezones,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone number must be in E.164 format (e.g. +17096789000).',
            'timezone_id.exists' => 'The selected timezone is invalid.',
            'project_ids.*.exists' => 'One or more selected projects are invalid.',
        ];
    }
}
