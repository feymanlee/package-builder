<?php

/*
 * This file is part of the overtrue/package-builder.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\PackageBuilder\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class BuildCommand.
 *
 * @author overtrue <i@overtrue.me>
 */
class BuildCommand extends Command
{
    /**
     * @var string
     */
    protected $stubsDirectory;

    /**
     * @var string
     */
    protected $packageDirectory;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $fs;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('build')
            ->setDescription('Build package')
            ->addArgument(
                'directory',
                InputArgument::OPTIONAL,
                'Directory that contains composer-driven project'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->fs = new Filesystem();
        $this->stubsDirectory = __DIR__.'/../stubs/';

        $helper = $this->getHelper('question');

        $config = [
                    'name' => 'Package Name',
                    'namespace' => '',
                    'phpunit' => true,
                    'phpcs' => true,
                    'phpcs_standards' => 'symfony',
                  ];

        $question = new Question('Please enter the name of the package (example: foo/bar): ');
        $question->setValidator(function ($value) {
            if (trim($value) == '') {
                throw new \Exception('The package name can not be empty');
            }

            if (!preg_match('/[a-z0-9\-_]+\/[a-z0-9\-_]+/', $value)) {
                throw new \Exception('The package name is invalid, format: vendor/product');
            }

            return $value;
        });
        $question->setMaxAttempts(5);

        $config['name'] = $helper->ask($input, $output, $question);

        $defaultNamespace = implode('\\', array_map('ucfirst', explode('/', $config['name'])));

        $question = new Question("Please enter the namespace of the package [<fg=yellow>{$defaultNamespace}</fg=yellow>]: ", $defaultNamespace);
        $config['namespace'] = $helper->ask($input, $output, $question);

        $question = new ConfirmationQuestion('Do you want to test this package ?[<fg=yellow>Y/n</fg=yellow>]:', 'yes');
        $config['phpunit'] = $helper->ask($input, $output, $question);

        $question = new ConfirmationQuestion('Do you want to use php-cs-fixer format you code ? [<fg=yellow>Y/n</fg=yellow>]:', 'yes');
        $config['phpcs'] = $helper->ask($input, $output, $question);

        if ($config['phpcs']) {
            $question = new Question('Please enter the standard of php-cs-fixer [symfony] ?', 'symfony');
            $config['phpcs_standards'] = $helper->ask($input, $output, $question);
        }

        $directory = './'.$input->getArgument('directory');
        $this->packageDirectory = $directory.str_replace(['/'], '-', $config['name']);

        $this->createPackage($config);

        if ($config['phpunit']) {
            $this->copyPHPUnitFile($config);
        }
        if ($config['phpcs']) {
            $this->createCSFixerConfiguration($config);
        }

        $this->initComposer($config);
    }

    /**
     * Create package directory and base files.
     *
     * @param array $config
     *
     * @return string
     */
    protected function createPackage(array $config)
    {
        $this->fs->mkdir($this->packageDirectory.'/src/', 0755);
        $this->createReadme($config['name']);
        $this->fs->touch($this->packageDirectory.'/src/.gitkeep');
        $this->copyFile('gitattributes', '.gitattributes');
        $this->copyFile('gitignore', '.gitignore');
        $this->copyFile('editorconfig', '.editorconfig');

        return $this->packageDirectory;
    }

    /**
     * Create README.md.
     *
     * @param string $name
     */
    protected function createReadme($name)
    {
        $name = ucfirst(explode('/', $name)[1]);
        $readme = <<<README
# $name

...

## Installing

...

## Usage

...

## License

MIT

README;

        $this->fs->dumpFile($this->packageDirectory.'/README.md', $readme);
    }

    /**
     * Create PHPUnit files.
     */
    protected function copyPHPUnitFile()
    {
        $this->fs->mkdir($this->packageDirectory.'/tests');
        $this->fs->touch($this->packageDirectory.'/tests/.gitkeep');
        $this->copyFile('phpunit_config', 'phpunit.xml.dist');
    }

    /**
     * Create PHP-CS-fixer.
     *
     * @param array $config
     */
    protected function createCSFixerConfiguration($config)
    {
        $template = file_get_contents($this->stubsDirectory.'/php_cs');

        $content = str_replace('STANDARDS', var_export((array) $config['phpcs_standards'], true), $template);

        $this->fs->dumpFile($this->packageDirectory.'/.php_cs', $content);
    }

    /**
     * Init composer.
     *
     * @param array $config
     */
    protected function initComposer($config)
    {
        exec("composer init --name {$config['name']} --working-dir {$this->packageDirectory}");
    }

    /**
     * Copy file.
     *
     * @param string $file
     * @param string $filename
     *
     * @internal param string $directory
     */
    protected function copyFile($file, $filename = '')
    {
        $target = $this->packageDirectory.'/'.($filename ?: $file);

        $this->fs->copy($this->stubsDirectory.$file, $target, true);
    }
}
