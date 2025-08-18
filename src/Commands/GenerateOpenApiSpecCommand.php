<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Process;
use OpenApi\Util;
use Xentral\LaravelApi\OpenApiGeneratorFactory;

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

            $generator = $factory->create($config);
            $openApi = $generator->generate(Util::finder($config['folders']));

            $openApi->saveAs($config['output']);
            foreach ($config['validation_commands'] as $cmd) {
                $result = Process::run($cmd, function (string $type, string $output) {
                    echo $output;
                });
                if ($result->failed()) {
                    return self::FAILURE;
                }
            }
        }

        return self::SUCCESS;
    }
}
