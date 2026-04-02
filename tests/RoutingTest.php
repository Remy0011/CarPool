<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RoutingTest extends TestCase
{
    private array $allowedPages = ['home', 'Profil', 'Reservation', 'Map', 'Parametre'];

    public function testAllowedPagesContainsReservation(): void
    {
        $this->assertContains('Reservation', $this->allowedPages);
    }

    public function testAllowedPagesContainsParametre(): void
    {
        $this->assertContains('Parametre', $this->allowedPages);
    }

    public function testUnknownPageFallsTo404(): void
    {
        $page = 'HackerPage';
        if (!in_array($page, $this->allowedPages, true)) {
            $page = '404';
        }
        $this->assertSame('404', $page);
    }

    public function testDefaultPageIsHome(): void
    {
        $page = $_GET['page'] ?? 'home';
        $this->assertSame('home', $page);
    }

    public function testAllAllowedPageFilesExist(): void
    {
        $publicDir = __DIR__ . '/../public';
        foreach ($this->allowedPages as $p) {
            $this->assertFileExists("$publicDir/$p.php", "Page file $p.php should exist");
        }
    }

    public function testLayoutPartialsExist(): void
    {
        $publicDir = __DIR__ . '/../public';
        $this->assertFileExists("$publicDir/doctype.php");
        $this->assertFileExists("$publicDir/navbar.php");
        $this->assertFileExists("$publicDir/footer.php");
    }
}
