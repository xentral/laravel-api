<?php declare(strict_types=1);

use Symfony\Component\Finder\Finder;
use Xentral\LaravelApi\OpenApi\OpenApiGeneratorFactory;

it('matches the fixture', function () {
    $factory = new OpenApiGeneratorFactory;
    $config = config('openapi.schemas.default');
    $generator = $factory->create($config);
    $actualYaml = $generator->generate(Finder::create()->in($config['config']['folders'])->files())->toYaml();
    $expectedYamlPath = $config['config']['output'];
    if (! file_exists($expectedYamlPath)) {
        file_put_contents($expectedYamlPath, $actualYaml);
        $this->markTestIncomplete('Expected YAML file does not exist. Created it for you. Please run test again.');
    }

    expect($actualYaml)->toBe(file_get_contents($expectedYamlPath));
});
