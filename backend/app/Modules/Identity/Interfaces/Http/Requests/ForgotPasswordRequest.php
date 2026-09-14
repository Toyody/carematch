<?php

namespace App\Modules\Identity\Interfaces\Http\Requests;

use App\Modules\Identity\Application\Support\EmailNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class ForgotPasswordRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
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
