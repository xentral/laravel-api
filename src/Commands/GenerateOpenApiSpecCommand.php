<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Process;
use OpenApi\Util;
use Xentral\LaravelApi\OpenApi\OpenApiGeneratorFactory;

class GenerateOpenApiSpecCommand extends Command
{
    protected $signature = 'openapi:generate {schema?}';

    public function handle(OpenApiGeneratorFactory $factory, Repository $config): int
    {
        $schemas = $config->get('openapi.schemas');
        if ($schema = $this->argument('schema')) {
            $specified = $config->get('openapi.schemas.'.$schema);
            if (! $specified) {
                $this->error('Schema not found: '.$schema);

                return self::FAILURE;
            }
            $schemas = [$schema => $specified];
        }

        foreach ($schemas as $config) {
            $this->components->info('Generating OpenAPI spec for '.$config['name']);

            $generator = $factory->create($config);
            $openApi = $generator->generate(Util::finder($config['folders']));

            $openApi->saveAs($config['output']);
            $this->components->info('Generated OpenAPI spec and stored in '.$config['output']);
            foreach ($config['validation_commands'] as $cmd) {
                $this->components->info('Running validation command: '.$cmd);
                $result = Process::run($cmd, function (string $type, string $output) {
                    echo $output;
                });
                if ($result->failed()) {
                    return self::FAILURE;
                }
            }
        }
        $this->components->info('Done!');

        return self::SUCCESS;
    }
}
