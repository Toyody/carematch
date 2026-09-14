<?php

namespace App\Modules\Identity\Interfaces\Http\Resources;

use App\Modules\Identity\Infrastructure\Persistence\User;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, int|string>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $createdAt = $user->getAttribute('created_at');

        if (! $createdAt instanceof DateTimeInterface) {
            throw new LogicException('The Identity user has no creation timestamp.');
        }

        return [
            'id' => (int) $user->getKey(),
            'name' => (string) $user->getAttribute('name'),
            'email' => (string) $user->getAttribute('email'),
            'created_at' => $createdAt->format(DATE_ATOM),
        ];
    }
}
