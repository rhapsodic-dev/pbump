<?php

declare(strict_types=1);

require_once __DIR__ . '/ReleaseFixtureOptions.php';

use PHPUnit\Framework\TestCase;
use Rhapsodic\Pbump\Release\Dto\ProcessResult;
use Rhapsodic\Pbump\Release\Dto\ReleaseConfig;

abstract class ReleaseTestCase extends TestCase
{
    /** @var list<string> */
    private array $tempDirs = [];
    protected string $toolRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->toolRoot = dirname(__DIR__, 1);
        $this->toolRoot = dirname($this->toolRoot);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $path) {
            $this->deleteDirectory($path);
        }

        $this->tempDirs = [];

        parent::tearDown();
    }

    /**
     * @param list<string> $commitMessages
     * @param array<string, mixed> $composer
     */
    protected function createRepoFixture(
        array $commitMessages,
        ?string $initialTag = null,
        bool $withRemote = true,
        array $composer = []
    ): string {
        $repo = $this->createBareFixture(new ReleaseFixtureOptions(
            composer: array_replace([
                'name' => 'rhapsodic/pbump',
            ], $composer),
        ));

        $this->git($repo, ['init']);
        $this->git($repo, ['config', 'user.name', 'Test Runner']);
        $this->git($repo, ['config', 'user.email', 'tests@example.com']);
        $this->git($repo, ['config', 'commit.gpgsign', 'false']);
        $this->git($repo, ['config', 'tag.gpgSign', 'false']);
        $this->git($repo, ['add', '.']);
        $this->git($repo, ['commit', '-m', 'chore: initial state']);

        if ($initialTag !== null) {
            $this->git($repo, ['tag', $initialTag]);
        }

        foreach ($commitMessages as $index => $message) {
            $file = $repo . DIRECTORY_SEPARATOR . 'changes' . DIRECTORY_SEPARATOR . sprintf('change-%02d.txt', $index + 1);
            $this->ensureDirectory(dirname($file));
            file_put_contents($file, $message . PHP_EOL);
            $this->git($repo, ['add', '.']);
            $this->git($repo, ['commit', '-m', $message]);
        }

        if ($withRemote) {
            $this->attachBareRemote($repo);
        }

        return $repo;
    }

    protected function createBareFixture(?ReleaseFixtureOptions $options = null): string
    {
        $baseDir = $this->createTempDirectory('release-tests-');
        $options ??= new ReleaseFixtureOptions();

        if ($options->composer !== []) {
            $composerJson = json_encode($options->composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            file_put_contents($baseDir . DIRECTORY_SEPARATOR . 'composer.json', $composerJson);
        }

        return $baseDir;
    }

    protected function createTempDirectory(string $prefix): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(6));
        $this->tempDirs[] = $path;
        $this->ensureDirectory($path);

        return $path;
    }

    protected function attachBareRemote(string $repo): void
    {
        $this->attachBareRemoteAs($repo, 'origin');
    }

    protected function attachBareRemoteAs(string $repo, string $remoteName): void
    {
        $remote = $this->createTempDirectory('release-remote-') . DIRECTORY_SEPARATOR . $remoteName . '.git';
        $this->runGitCommand(['init', '--bare', $remote], $repo);
        $this->git($repo, ['remote', 'add', $remoteName, $remote]);

        $branch = $this->gitOutput($repo, ['branch', '--show-current']);
        $this->git($repo, ['push', '-u', $remoteName, $branch]);
        $this->git($repo, ['push', $remoteName, '--tags']);
    }

    protected function writeReleaseConfig(string $repo, ReleaseConfig $config): void
    {
        $data = [];

        if ($config->type !== null) {
            $data['type'] = $config->type;
        }

        if ($config->dryRun !== null) {
            $data['dryRun'] = $config->dryRun;
        }

        if ($config->tag !== null) {
            $data['tag'] = $config->tag;
        }

        if ($config->push !== null) {
            $data['push'] = $config->push;
        }

        if ($config->yes !== null) {
            $data['yes'] = $config->yes;
        }

        if ($config->quiet !== null) {
            $data['quiet'] = $config->quiet;
        }

        if ($config->versionSource !== null) {
            $data['versionSource'] = $config->versionSource;
        }

        if ($config->allowDirty !== null) {
            $data['allowDirty'] = $config->allowDirty;
        }

        $configJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($repo . DIRECTORY_SEPARATOR . '.pbump.config.json', $configJson);
    }

    protected function installPackageAsDependency(string $consumer): void
    {
        $vendorDir = $consumer . DIRECTORY_SEPARATOR . 'vendor';
        $packageDir = $vendorDir . DIRECTORY_SEPARATOR . 'rhapsodic' . DIRECTORY_SEPARATOR . 'pbump';
        $autoloadPath = $vendorDir . DIRECTORY_SEPARATOR . 'autoload.php';
        $proxyPath = $vendorDir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'pbump';

        $this->ensureDirectory($vendorDir . DIRECTORY_SEPARATOR . 'bin');
        $this->copyDirectory($this->toolRoot . DIRECTORY_SEPARATOR . 'bin', $packageDir . DIRECTORY_SEPARATOR . 'bin');
        $this->copyDirectory($this->toolRoot . DIRECTORY_SEPARATOR . 'scripts', $packageDir . DIRECTORY_SEPARATOR . 'scripts');
        $this->copyDirectory($this->toolRoot . DIRECTORY_SEPARATOR . 'src', $packageDir . DIRECTORY_SEPARATOR . 'src');
        copy($this->toolRoot . DIRECTORY_SEPARATOR . 'composer.json', $packageDir . DIRECTORY_SEPARATOR . 'composer.json');

        file_put_contents(
            $autoloadPath,
            "<?php\n\ndeclare(strict_types=1);\n\nreturn require "
            . var_export($this->toolRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php', true)
            . ";\n"
        );

        file_put_contents(
            $proxyPath,
            "#!/usr/bin/env php\n<?php\n\ndeclare(strict_types=1);\n\n"
            . "\$GLOBALS['_composer_bin_dir'] = __DIR__;\n"
            . "\$GLOBALS['_composer_autoload_path'] = __DIR__ . '/../autoload.php';\n\n"
            . "return include __DIR__ . '/../rhapsodic/pbump/bin/pbump';\n"
        );
    }

    /**
     * @param list<string> $args
     *
     */
    protected function runRelease(string $repo, array $args): ProcessResult
    {
        $command = array_merge(
            [PHP_BINARY, $this->toolRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'release.php'],
            $args
        );

        return $this->runCommand($command, $repo, [
            'RELEASE_PROJECT_ROOT' => $repo,
            'RELEASE_DISABLE_INTERACTION' => '1',
        ]);
    }

    /**
     * @param list<string> $args
     * @param array<string, string> $env
     *
     */
    protected function runReleaseEntrypoint(array $args, string $cwd, array $env = []): ProcessResult
    {
        $command = array_merge(
            [PHP_BINARY, $this->toolRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'release.php'],
            $args
        );

        return $this->runCommand(
            $command,
            $cwd,
            array_merge(['RELEASE_DISABLE_INTERACTION' => '1'], $env)
        );
    }

    /**
     * @param list<string> $args
     * @param array<string, string> $env
     *
     */
    protected function runPackageBinary(string $cwd, array $args, array $env = []): ProcessResult
    {
        $command = array_merge(
            [PHP_BINARY, $this->toolRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'pbump'],
            $args
        );

        return $this->runCommand(
            $command,
            $cwd,
            array_merge(['RELEASE_DISABLE_INTERACTION' => '1'], $env)
        );
    }

    /**
     * @param list<string> $args
     * @param array<string, string> $env
     *
     */
    protected function runReleaseCli(string $projectRoot, array $args, ?string $cwd = null, array $env = []): ProcessResult
    {
        $runnerPath = $this->createTempDirectory('release-cli-runner-') . DIRECTORY_SEPARATOR . 'run-release-cli.php';
        $script = <<<'PHP'
<?php

declare(strict_types=1);

require %s;

$cli = new \Rhapsodic\Pbump\ReleaseCli();
$argv = %s;
$projectRoot = %s;

exit($cli->run($argv, $projectRoot));
PHP;

        file_put_contents(
            $runnerPath,
            sprintf(
                $script,
                var_export($this->toolRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php', true),
                var_export(array_merge(['pbump'], $args), true),
                var_export($projectRoot, true),
            )
        );

        return $this->runCommand(
            [PHP_BINARY, $runnerPath],
            $cwd ?? $projectRoot,
            array_merge(['RELEASE_DISABLE_INTERACTION' => '1'], $env)
        );
    }

    /**
     * @param list<string> $args
     *
     */
    protected function runInstalledRelease(string $consumer, string $cwd, array $args): ProcessResult
    {
        $command = array_merge(
            [PHP_BINARY, $consumer . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'pbump'],
            $args
        );

        return $this->runCommand($command, $cwd, [
            'RELEASE_PROJECT_ROOT' => '',
            'RELEASE_AUTOLOAD_PATH' => '',
            'RELEASE_DISABLE_INTERACTION' => '1',
        ]);
    }

    /**
     * @param list<string> $args
     */
    protected function git(string $repo, array $args): void
    {
        $this->runGitCommand($args, $repo);
    }

    /**
     * @param list<string> $args
     */
    protected function runGitCommand(array $args, string $cwd): void
    {
        $result = $this->runCommand(array_merge(['git'], $args), $cwd);
        if ($result->exitCode !== 0) {
            $message = trim($result->stderr !== '' ? $result->stderr : $result->stdout);
            throw new RuntimeException("Git command failed: {$message}");
        }
    }

    /**
     * @param list<string> $args
     */
    protected function gitOutput(string $repo, array $args): string
    {
        $result = $this->runCommand(array_merge(['git'], $args), $repo);
        if ($result->exitCode !== 0) {
            $message = trim($result->stderr !== '' ? $result->stderr : $result->stdout);
            throw new RuntimeException("Git command failed: {$message}");
        }

        return trim($result->stdout);
    }

    /**
     * @param list<string> $args
     */
    protected function gitDirOutput(string $gitDir, array $args): string
    {
        $result = $this->runCommand(
            array_merge(['git', '--git-dir', $gitDir], $args),
            dirname($gitDir)
        );

        if ($result->exitCode !== 0) {
            $message = trim($result->stderr !== '' ? $result->stderr : $result->stdout);
            throw new RuntimeException("Git command failed: {$message}");
        }

        return trim($result->stdout);
    }

    protected function gitTagExists(string $repo, string $tag): bool
    {
        return $this->gitOutput($repo, ['tag', '--list', $tag]) === $tag;
    }

    protected function remoteTagExists(string $repo, string $tag): bool
    {
        return $this->remoteTagExistsOn($repo, 'origin', $tag);
    }

    protected function remoteTagExistsOn(string $repo, string $remoteName, string $tag): bool
    {
        $remote = $this->remoteUrl($repo, $remoteName);

        return $this->gitDirOutput($remote, ['tag', '--list', $tag]) === $tag;
    }

    protected function remoteHeadSubject(string $repo): string
    {
        return $this->remoteHeadSubjectOn($repo, 'origin');
    }

    /**
     * @return list<string>
     */
    protected function headChangedFiles(string $repo): array
    {
        $output = $this->gitOutput($repo, ['show', '--name-only', '--pretty=', 'HEAD']);
        if ($output === '') {
            return [];
        }

        $files = preg_split('/\r?\n/', $output) ?: [];

        return array_values(array_filter($files, static fn (string $file): bool => $file !== ''));
    }

    protected function remoteHeadSubjectOn(string $repo, string $remoteName): string
    {
        $remote = $this->remoteUrl($repo, $remoteName);
        $branch = $this->gitOutput($repo, ['branch', '--show-current']);

        return $this->gitDirOutput($remote, ['log', '-1', '--pretty=%s', 'refs/heads/' . $branch]);
    }

    protected function originUrl(string $repo): string
    {
        return $this->remoteUrl($repo, 'origin');
    }

    protected function remoteUrl(string $repo, string $remoteName): string
    {
        return $this->gitOutput($repo, ['config', '--get', "remote.{$remoteName}.url"]);
    }

    protected function readComposerContents(string $repo): string
    {
        $contents = file_get_contents($repo . DIRECTORY_SEPARATOR . 'composer.json');
        if (!is_string($contents)) {
            throw new RuntimeException('Failed to read composer.json');
        }

        return $contents;
    }

    protected function readComposerVersion(string $repo): string
    {
        $composer = json_decode($this->readComposerContents($repo), true);
        if (!is_array($composer) || !isset($composer['version']) || !is_string($composer['version'])) {
            throw new RuntimeException('Failed to read version from composer.json');
        }

        return $composer['version'];
    }

    protected function latestTaggedVersion(string $repo): string
    {
        $result = $this->runRelease($repo, ['-v']);
        if ($result->exitCode !== 0) {
            $message = trim($result->stderr !== '' ? $result->stderr : $result->stdout);
            throw new RuntimeException("Release command failed: {$message}");
        }

        return trim($result->stdout);
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $env
     *
     */
    protected function runCommand(array $command, string $cwd, array $env = []): ProcessResult
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $options = [];
        if (PHP_OS_FAMILY === 'Windows') {
            $options['bypass_shell'] = true;
        }

        $processEnv = null;
        if ($env !== []) {
            $baseEnv = getenv();
            $processEnv = $baseEnv === false ? $env : array_merge($baseEnv, $env);
        }

        $process = proc_open($command, $descriptors, $pipes, $cwd, $processEnv, $options);
        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start process.');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return new ProcessResult($exitCode, $stdout ?: '', $stderr ?: '');
    }

    protected function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException("Failed to create directory: {$path}");
        }
    }

    protected function copyDirectory(string $source, string $destination): void
    {
        $items = scandir($source);
        if ($items === false) {
            throw new RuntimeException("Failed to read directory: {$source}");
        }

        $this->ensureDirectory($destination);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
            $destinationPath = $destination . DIRECTORY_SEPARATOR . $item;

            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destinationPath);
                continue;
            }

            if (!copy($sourcePath, $destinationPath)) {
                throw new RuntimeException("Failed to copy file: {$sourcePath}");
            }
        }
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath) && !is_link($itemPath)) {
                $this->deleteDirectory($itemPath);
                continue;
            }

            @chmod($itemPath, 0777);
            @unlink($itemPath);
        }

        @chmod($path, 0777);
        @rmdir($path);
    }
}
