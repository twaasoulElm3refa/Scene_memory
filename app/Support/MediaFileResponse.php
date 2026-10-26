<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Inline media file responses. Published media may be cached by browsers and
 * shared caches for media_security.public_max_age seconds, with Last-Modified
 * revalidation; everything else is private and never stored.
 */
final class MediaFileResponse
{
    public static function make(Request $request, string $path, bool $published): BinaryFileResponse
    {
        $maxAge = (int) config('media_security.public_max_age', 0);
        if (! $published || $maxAge < 1) {
            return PrivateFileResponse::make($path);
        }

        $response = new BinaryFileResponse($path, 200, ['X-Content-Type-Options' => 'nosniff'], true, null, false, true);
        $response->setPublic();
        $response->setMaxAge($maxAge);
        $response->isNotModified($request);

        return $response;
    }
}
