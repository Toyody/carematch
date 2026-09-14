<?php

namespace App\Modules\Identity\Interfaces\Http\Requests;

use App\Modules\Identity\Application\Support\EmailNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required',
                Password::defaults(),
                'confirmed',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (! is_string($email)) {
            return;
        }

        $normalizer = $this->container->make(EmailNormalizer::class);

        $this->merge([
            'email' => $normalizer->normalize($email),
        ]);
    }
}
