<?php

declare(strict_types=1);

namespace App\Http\Middlewares;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompressResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        $response = $next($request);

        if ($response instanceof BinaryFileResponse) {
            return $response;
        }

        if (str_contains($request->header('Accept-Encoding'), 'gzip')
            && !in_array('Content-Encoding', $response->headers->keys(), true)) {

            $output = $response->getContent();

            if (strlen($output) > 1024) {
                $compressed = gzencode($output, 9);

                if ($compressed !== false && strlen($compressed) < strlen($output)) {
                    $response->setContent($compressed);
                    $response->headers->add([
                        'Content-Encoding' => 'gzip',
                        'X-Compression-Rate' => round((1 - strlen($compressed) / strlen($output)) * 100, 2).'%',
                    ]);
                }
            }
        }

        return $response;
    }
}
