<?php

namespace App\Services\Dispatch;

use App\Models\RouteAccessToken;

class PublicRouteAccessService
{
    public function resolve(string $token, array $with = []): RouteAccessToken
    {
        /** @var RouteAccessToken $accessToken */
        $accessToken = RouteAccessToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->with($with)
            ->firstOrFail();

        $accessToken->update(['last_used_at' => now()]);

        return $accessToken;
    }
}
