<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Rhapsodic\Pbump\Release\Dto\ReleaseConfig;
use Rhapsodic\Pbump\Release\OptionsResolver;

final class OptionsResolverTest extends TestCase
{
    public function testResolveOptionsPrefersCliValuesOverConfig(): void
    {
        $resolver = new OptionsResolver();
        $input = new ArgvInput([
            'pbump',
            '--version-source=tag',
            '--dry-run',
            '--type=patch',
            '--no-tag',
            '--no-push',
            '--yes',
            '--quiet',
            '--allow-dirty',
        ], $resolver->buildInputDefinition());

        $options = $resolver->resolveOptions($input, new ReleaseConfig(
            type: 'minor',
            dryRun: false,
            tag: 'release-1.3.0',
            push: true,
            yes: false,
            quiet: false,
            versionSource: 'composer',
            allowDirty: false,
        ));

        self::assertSame('tag', $options->versionSource);
        self::assertTrue($options->dryRun);
        self::assertSame('patch', $options->forcedType);
        self::assertFalse($options->tag);
        self::assertFalse($options->push);
        self::assertTrue($options->yes);
        self::assertTrue($options->quiet);
        self::assertTrue($options->allowDirty);
    }

    public function testResolveOptionsFallsBackToConfigAndDefaults(): void
    {
        $resolver = new OptionsResolver();
        $input = new ArgvInput(['pbump'], $resolver->buildInputDefinition());

        $options = $resolver->resolveOptions($input, new ReleaseConfig(
            type: 'minor',
            dryRun: true,
            tag: 'release-1.3.0',
            push: false,
            yes: true,
            quiet: true,
            versionSource: 'composer',
            allowDirty: true,
        ));

        self::assertFalse($options->showHelp);
        self::assertFalse($options->showVersion);
        self::assertSame('composer', $options->versionSource);
        self::assertTrue($options->dryRun);
        self::assertSame('minor', $options->forcedType);
        self::assertSame('release-1.3.0', $options->tag);
        self::assertFalse($options->push);
        self::assertTrue($options->yes);
        self::assertTrue($options->quiet);
        self::assertTrue($options->allowDirty);
    }

    public function testResolveOptionsRejectsInvalidVersionSource(): void
    {
        $resolver = new OptionsResolver();
        $input = new ArgvInput(['pbump', '--version-source=broken'], $resolver->buildInputDefinition());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid --version-source: broken.');

        $resolver->resolveOptions($input, new ReleaseConfig());
    }
}
