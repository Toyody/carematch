<?php

namespace App\Modules\Identity\Interfaces\Http\Requests;

use App\Modules\Identity\Application\Support\EmailNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ResetPasswordRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', Password::defaults(), 'confirmed'],
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
