<?php

namespace Azteck\ComposerWorkspaces;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    private Composer    $composer;
    private IOInterface $io;
    private string      $rootDir;
    private string      $modulesDir;

    // -------------------------------------------------------------------------
    // PluginInterface
    // -------------------------------------------------------------------------

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer   = $composer;
        $this->io         = $io;
        $this->rootDir    = realpath(dirname($composer->getConfig()->get('vendor-dir')));
        $this->modulesDir = $this->rootDir . '/Modules';
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}
    public function uninstall(Composer $composer, IOInterface $io): void {}

    // -------------------------------------------------------------------------
    // EventSubscriberInterface
    // -------------------------------------------------------------------------

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::PRE_AUTOLOAD_DUMP => ['onPreAutoloadDump', 10],
            ScriptEvents::POST_INSTALL_CMD  => ['onPostInstall', 0],
            ScriptEvents::POST_UPDATE_CMD   => ['onPostUpdate', 0],
        ];
    }

    public function onPreAutoloadDump(Event $event): void
    {
        $this->io->write('<info>[Workspaces] Injection des PSR-4 des modules...</info>');
        $this->injectAutoloads();
    }

    public function onPostInstall(Event $event): void
    {
        $this->installModules();
    }

    public function onPostUpdate(Event $event): void
    {
        $this->installModules();
    }

    // -------------------------------------------------------------------------
    // PSR-4 injection
    // -------------------------------------------------------------------------

    private function injectAutoloads(): void
    {
        $package     = $this->composer->getPackage();
        $autoload    = $package->getAutoload();
        $autoloadDev = $package->getDevAutoload();

        foreach ($this->findModules() as $modulePath) {
            $config = $this->readJson($modulePath . '/composer.json');
            if (!$config) {
                continue;
            }

            foreach ($config['autoload']['psr-4'] ?? [] as $namespace => $path) {
                $rel = $this->relative($this->rootDir, $modulePath . '/' . trim($path, '/'));
                $autoload['psr-4'][$namespace] = $rel . '/';
                $this->io->write("  <comment>PSR-4</comment> {$namespace} → {$rel}");
            }

            foreach ($config['autoload']['classmap'] ?? [] as $path) {
                $rel = $this->relative($this->rootDir, $modulePath . '/' . trim($path, '/'));
                $autoload['classmap'][] = $rel . '/';
            }

            foreach ($config['autoload-dev']['psr-4'] ?? [] as $namespace => $path) {
                $rel = $this->relative($this->rootDir, $modulePath . '/' . trim($path, '/'));
                $autoloadDev['psr-4'][$namespace] = $rel . '/';
                $this->io->write("  <comment>PSR-4 dev</comment> {$namespace} → {$rel}");
            }
        }

        $package->setAutoload($autoload);
        $package->setDevAutoload($autoloadDev);
    }

    // -------------------------------------------------------------------------
    // Installation des modules
    // -------------------------------------------------------------------------

    private function installModules(): void
    {
        foreach ($this->findModules() as $modulePath) {
            $config = $this->readJson($modulePath . '/composer.json');
            if (empty($config['require'])) {
                continue;
            }
            $this->installModule($modulePath);
        }
    }

    private function installModule(string $path): void
    {
        $vendorDir = $path . '/vendor';
        $lockFile  = $path . '/composer.lock';
        $jsonFile  = $path . '/composer.json';

        $installed = is_dir($vendorDir) && file_exists($lockFile);
        $outdated  = $installed && filemtime($jsonFile) > filemtime($lockFile);

        if ($installed && !$outdated) {
            $this->io->write(sprintf('  <comment>[skip]</comment> %s', basename($path)));
            return;
        }

        $this->io->write(sprintf('<info>[install]</info> %s...', basename($path)));

        $bin = $this->composerBin();
        $cmd = sprintf('%s install --no-interaction --prefer-dist --working-dir=%s 2>&1', $bin, escapeshellarg($path));
        exec($cmd, $output, $code);

        if ($code !== 0) {
            $this->io->writeError(sprintf('<error>Erreur %s</error>: %s', basename($path), implode("\n", $output)));
        } else {
            $this->io->write(sprintf('  <info>✓ %s</info>', basename($path)));
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function findModules(): array
    {
        if (!is_dir($this->modulesDir)) {
            return [];
        }

        $modules = [];
        foreach (new \DirectoryIterator($this->modulesDir) as $entry) {
            if (!$entry->isDot() && $entry->isDir() && file_exists($entry->getPathname() . '/composer.json')) {
                $modules[] = $entry->getPathname();
            }
        }

        return $modules;
    }

    private function readJson(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }
        return (new JsonFile($path))->read();
    }

    private function relative(string $from, string $to): string
    {
        $from = explode('/', rtrim(str_replace('\\', '/', $from), '/'));
        $to   = explode('/', rtrim(str_replace('\\', '/', $to), '/'));

        while (count($from) && count($to) && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return str_repeat('../', count($from)) . implode('/', $to);
    }

    private function composerBin(): string
    {
        if ($env = getenv('COMPOSER_BIN')) {
            return $env;
        }
        foreach (['composer', 'composer.phar'] as $bin) {
            exec("which $bin 2>/dev/null", $out, $code);
            if ($code === 0 && !empty($out[0])) {
                return $out[0];
            }
        }
        return 'composer';
    }
}
