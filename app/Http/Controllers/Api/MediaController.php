<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    public function __invoke(Media $media, ?string $conversion = null): Response
    {
        $path = $conversion
            ? $media->getPath($conversion)
            : $media->getPath();

        // Fall back to original if conversion doesn't exist yet
        if ($conversion && ! file_exists($path)) {
            $path = $media->getPath();
        }

        if (! file_exists($path)) {
            abort(404);
        }

        $lastModified = filemtime($path);
        $etag = '"'.md5($media->id.'-'.$lastModified).'"';

        // Handle conditional requests (304 Not Modified)
        $requestEtag = request()->header('If-None-Match');
        $requestModified = request()->header('If-Modified-Since');

        if ($requestEtag === $etag) {
            return response()->noContent(304);
        }

        if ($requestModified && strtotime($requestModified) >= $lastModified) {
            return response()->noContent(304);
        }

        return response()->file($path, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified).' GMT',
        ]);
    }
}
