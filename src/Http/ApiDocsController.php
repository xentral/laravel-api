<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Http;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class ApiDocsController
{
    public function assets(string $asset): Response
    {
        // Note: This method only serves Swagger UI assets. Scalar uses CDN.
        $allowedFiles = [
            'favicon-16x16.png',
            'favicon-32x32.png',
            'oauth2-redirect.html',
            'swagger-ui-bundle.js',
            'swagger-ui-bundle.js.map',
            'swagger-ui-standalone-preset.js',
            'swagger-ui-standalone-preset.js.map',
            'swagger-ui.css',
            'swagger-ui.css.map',
            'swagger-ui.js',
            'swagger-ui.js.map',
        ];
        if (! in_array($asset, $allowedFiles, true)) {
            abort(404, 'File not found');
        }

        $path = realpath(base_path('vendor/swagger-api/swagger-ui/dist/'.$asset));

        $response = new Response(
            File::get($path),
            200,
            [
                'Content-Type' => (isset(pathinfo($asset)['extension']) && pathinfo($asset)['extension'] === 'css')
                    ? 'text/css'
                    : 'application/javascript',
            ]
        );

        $response->setSharedMaxAge(31536000)
            ->setMaxAge(31536000)
            ->setExpires(new \DateTime('+1 year'));

        return $response;
    }

    public function schema(string $schema): JsonResponse
    {
        $path = config('openapi.schemas.'.$schema.'.config.output', '');

        if (! $path) {
            abort(404, 'Schema not found');
        }
        $spec = str_ends_with((string) $path, '.json')
            ? json_decode(File::get($path), true)
            : Yaml::parse(File::get($path));

        if (is_array($spec) && isset($spec['servers'])) {
            $spec['servers'][0]['url'] = config('app.url');
        }

        return new JsonResponse($spec);
    }

    public function docs(?string $schema = null): View
    {
        $schema = $schema ?: 'default';

        // Allow client override via query parameter (?client=scalar)
        $requestClient = request()->query('client');

        // Determine which client to use (query param > per-schema > global setting)
        $client = $requestClient
            ?? config("openapi.schemas.{$schema}.client", config('openapi.docs.client', 'swagger'));

        // Validate client type
        if (! in_array($client, ['swagger', 'scalar'], true)) {
            $client = 'swagger';
        }

        $viewName = $client === 'scalar' ? 'openapi::scalar' : 'openapi::docs';

        return view($viewName, [
            'title' => 'OpenAPI Docs: '.$schema,
            'api' => $schema,
            'url' => route('openapi.schema', ['schema' => $schema]),
            'client' => $client,
        ]);
    }
}
