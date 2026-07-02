<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Enforces the boundary that keeps the domain portable and testable: no Laravel/Illuminate
 * imports inside app/Domain (ENGINEERING_STANDARDS). Mirrors the Semgrep rule in
 * platform-github so the invariant is caught locally and in CI via `composer test`.
 */
final class DomainPurityTest extends TestCase
{
    public function test_domain_has_no_framework_imports(): void
    {
        $offenders = [];
        $domain = dirname(__DIR__, 2).'/app/Domain';
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($domain));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $src = (string) file_get_contents($file->getPathname());
            if (preg_match('/^use\s+Illuminate\\\\/m', $src) === 1
                || str_contains($src, 'Illuminate\\Support\\Facades')) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame([], $offenders, "Framework import in Domain: \n".implode("\n", $offenders));
    }

    public function test_application_ports_are_interfaces(): void
    {
        $ports = glob(dirname(__DIR__, 2).'/app/Application/Port/*.php');
        $this->assertNotEmpty($ports);
        foreach ($ports as $p) {
            $this->assertStringContainsString('interface ', (string) file_get_contents($p));
        }
    }
}
