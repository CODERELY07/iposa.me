<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class ServiceWorkerController extends Controller
{
    /**
     * Files every install caches, besides the Vite build output.
     *
     * @var list<string>
     */
    private const STATIC_FILES = [
        '/offline.html',
        '/manifest.webmanifest',
        '/favicon.svg',
        '/favicon.ico',
        '/apple-touch-icon.png',
        '/icons/icon-192.png',
        '/icons/icon-512.png',
    ];

    /**
     * The service worker script. Its version follows the Vite build, so every deploy
     * installs a fresh worker that precaches the new assets and drops the old ones.
     */
    public function __invoke(): Response
    {
        $manifestPath = public_path('build/manifest.json');
        $buildFiles = [];
        $version = 'dev';

        if (is_file($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
            $version = substr(md5_file($manifestPath), 0, 12);

            foreach ($manifest as $entry) {
                $buildFiles[] = '/build/'.$entry['file'];

                foreach ($entry['css'] ?? [] as $css) {
                    $buildFiles[] = '/build/'.$css;
                }
            }
        }

        $script = str_replace(
            ['__VERSION__', '__PRECACHE__'],
            [$version, json_encode(array_values(array_unique([...self::STATIC_FILES, ...$buildFiles])), JSON_UNESCAPED_SLASHES)],
            (string) file_get_contents(resource_path('pwa/sw.js')),
        );

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }
}
