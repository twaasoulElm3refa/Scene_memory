<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * File responses for protected media. Symfony marks every BinaryFileResponse as
 * `public` by default, which overrides a `private` Cache-Control header, so the
 * directives are set through the response API instead.
 */
class PrivateFileResponse
{
    public static function make(string $path, ?string $downloadName = null): BinaryFileResponse
    {
        $response = new BinaryFileResponse($path, 200, ['X-Content-Type-Options' => 'nosniff'], false);
        if ($downloadName !== null) {
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);
        }
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
