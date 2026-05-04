<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Support/ReleaseTestCase.php';

use Rhapsodic\Pbump\Release\Dto\ReleaseConfig;
use Rhapsodic\Pbump\Release\ProjectFiles;

final class ProjectFilesTest extends ReleaseTestCase
{
    public function testLoadComposerConfigReadsJsonFile(): void
    {
        $dir = $this->createTempDirectory('project-files-');
        $path = $dir . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($path, "{\"name\":\"demo/package\"}\n");

        $files = new ProjectFiles();

        self::assertSame(['name' => 'demo/package'], $files->loadComposerConfig($path));
    }

    public function testLoadReleaseConfigReturnsEmptyDtoWhenMissing(): void
    {
        $dir = $this->createTempDirectory('project-files-');
        $files = new ProjectFiles();

        self::assertEquals(new ReleaseConfig(), $files->loadReleaseConfig($dir . DIRECTORY_SEPARATOR . '.pbump.config.json'));
    }

    public function testLoadReleaseConfigValidatesSchema(): void
    {
        $dir = $this->createTempDirectory('project-files-');
        $path = $dir . DIRECTORY_SEPARATOR . '.pbump.config.json';
        file_put_contents($path, "{\"tag\":\"\"}\n");

        $files = new ProjectFiles();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "tag" key in .pbump.config.json must not be an empty string.');

        $files->loadReleaseConfig($path);
    }

    public function testWriteComposerVersionPreservesFormattingAndNestedValues(): void
    {
        $dir = $this->createTempDirectory('project-files-');
        $path = $dir . DIRECTORY_SEPARATOR . 'composer.json';
        $contents = <<<'JSON'
{
    "name": "demo/package",
    "version": "1.2.3",
    "extra": {
        "version": "keep-me"
    }
}
JSON;
        file_put_contents($path, $contents . PHP_EOL);

        $files = new ProjectFiles();
        $files->writeComposerVersion($path, '1.2.4');

        self::assertSame(
            <<<'JSON'
{
    "name": "demo/package",
    "version": "1.2.4",
    "extra": {
        "version": "keep-me"
    }
}
JSON . PHP_EOL,
            file_get_contents($path)
        );
    }

    public function testWriteComposerVersionFailsWhenTopLevelFieldIsMissing(): void
    {
        $dir = $this->createTempDirectory('project-files-');
        $path = $dir . DIRECTORY_SEPARATOR . 'composer.json';
        file_put_contents($path, "{\"name\":\"demo/package\"}\n");

        $files = new ProjectFiles();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to update the "version" field in composer.json');

        $files->writeComposerVersion($path, '1.2.4');
    }
}
