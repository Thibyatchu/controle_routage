<?php

use App\Kernel;
use DoctrineMigrations\Version20241207183657;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv('APP_ENV', 'APP_DEBUG'))->bootEnv(dirname(__DIR__).'/.env.test');

// Clean up from previous runs
(new Filesystem())->remove([__DIR__ . '/../var/cache/test']);
(new Filesystem())->remove([__DIR__ . '/../var/sessions/test']);

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$output = new ConsoleOutput();
$application = new Application($kernel);
$application->setAutoExit(false);
$application->setCatchExceptions(false);

$runCommand = static function (string $name, array $options = []) use ($application): void {
    $input = new ArrayInput(array_merge(['command' => $name, '--env' => 'test'], $options));
    $input->setInteractive(false);
    $application->run($input);
};

$runCommand('doctrine:database:drop');

$runCommand('doctrine:database:create', [
    '--if-not-exists' => true,
]);
$runCommand('doctrine:schema:drop', [
    '--force' => true,
    '--full-database' => true,
]);
$runCommand('doctrine:schema:create');
$runCommand('doctrine:migrations:execute', [
    '--version' => [Version20241207183657::class],
]);
$runCommand('doctrine:fixtures:load', [
    '--group' => ['CodeceptionFixtures'],
    '--env' => 'test',
    '--no-interaction' => true,
]);

$kernel->shutdown();