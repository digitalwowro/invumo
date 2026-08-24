<?php

declare(strict_types=1);

namespace Tests\Support;

use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Execution;
use Pest\Browser\Support\Screenshot;
use Pest\Plugins\Snapshot;
use Pest\TestSuite;
use PHPUnit\Framework\Assert;
use Symfony\Component\Process\Process;

final class VisualSnapshot
{
    public static function assertMatches(
        AwaitableWebpage $webpage,
        bool $fullPage = true,
    ): void {
        $page = $webpage->page();

        $page->addStyleTag(<<<'CSS'
            * {
                transition: none !important;
                animation: none !important;
                font-family: Arial, sans-serif !important;
            }

            body {
                -webkit-font-smoothing: antialiased !important;
                -moz-osx-font-smoothing: grayscale !important;
            }
            CSS);
        $page->waitForLoadState('networkidle');
        $page->waitForFunction('document.readyState === "complete"');
        Execution::instance()->wait(0.1);

        $filename = $page->screenshot($fullPage);

        Assert::assertNotNull($filename, 'Unable to capture visual snapshot.');

        $actualPath = Screenshot::path($filename);
        $actual = file_get_contents($actualPath);

        Assert::assertIsString($actual, 'Unable to read visual snapshot.');

        $snapshots = TestSuite::getInstance()->snapshots;
        $snapshots->startNewExpectation();
        $encodedActual = base64_encode($actual);

        if (! $snapshots->has() || Snapshot::$updateSnapshots) {
            $snapshotPath = $snapshots->save($encodedActual);
            $change = Snapshot::$updateSnapshots ? 'updated' : 'created';

            TestSuite::getInstance()->registerSnapshotChange(
                "Snapshot {$change} at [{$snapshotPath}]",
            );
            @unlink($actualPath);

            return;
        }

        [$snapshotPath] = $snapshots->get();
        $artifactDirectory = Screenshot::dir().'/ImageDiffView';
        $artifactName = pathinfo($filename, PATHINFO_FILENAME);
        $process = new Process([
            'node',
            base_path('scripts/compare-visual-snapshot.mjs'),
            $actualPath,
            base_path($snapshotPath),
            $artifactDirectory,
            $artifactName,
        ]);
        $process->run();

        if ($process->isSuccessful()) {
            @unlink($actualPath);

            return;
        }

        $details = trim($process->getErrorOutput());
        $message = $process->getExitCode() === 1
            ? "Visual snapshot differs: {$details}"
            : "Visual snapshot comparison could not run: {$details}";

        Assert::fail($message);
    }
}
