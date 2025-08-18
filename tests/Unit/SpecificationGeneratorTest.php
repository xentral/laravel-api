<?php declare(strict_types=1);

use Symfony\Component\Finder\Finder;
use Xentral\LaravelApi\OpenApiGeneratorFactory;

it('matches the fixture', function () {
    $factory = new OpenApiGeneratorFactory;
    $config = require fixture('config.php');
    $generator = $factory->create($config);
    $actualYaml = $generator->generate(Finder::create()->in($config['folders'])->files())->toYaml();
    $expectedYamlPath = fixture('expected.yml');
    if (! file_exists($expectedYamlPath)) {
        file_put_contents($expectedYamlPath, $actualYaml);
        $this->markTestIncomplete('Expected YAML file does not exist. Created it for you. Please run test again.');
    }

    expect($actualYaml)->toBe(file_get_contents($expectedYamlPath));
});
