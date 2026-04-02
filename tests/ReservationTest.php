<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ReservationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
    }

    public function testViewTrajetsConducteursExists(): void
    {
        $stmt = $this->pdo->query('SELECT * FROM view_trajets_conducteurs LIMIT 1');
        $this->assertIsArray($stmt->fetch());
    }

    public function testViewTrajetsConducteursHasExpectedColumns(): void
    {
        $stmt = $this->pdo->query('SELECT * FROM view_trajets_conducteurs LIMIT 1');
        $row = $stmt->fetch();
        $this->assertArrayHasKey('journeys_id', $row);
        $this->assertArrayHasKey('start', $row);
        $this->assertArrayHasKey('final', $row);
        $this->assertArrayHasKey('conducteur_nom', $row);
        $this->assertArrayHasKey('place_available', $row);
    }

    public function testSeededJourneysExist(): void
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) AS cnt FROM journeys');
        $count = $stmt->fetch()['cnt'];
        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testPassengerCanBeFoundByEmail(): void
    {
        $stmt = $this->pdo->prepare('
            SELECT p.passengers_id
            FROM passengers p
            JOIN employees e ON p.employees_id = e.employees_id
            JOIN users u ON e.users_id = u.users_id
            WHERE u.user_email = ?
        ');
        $stmt->execute(['bob@example.com']);
        $passenger = $stmt->fetch();
        $this->assertNotEmpty($passenger);
        $this->assertArrayHasKey('passengers_id', $passenger);
    }

    public function testConductorIsNotAPassenger(): void
    {
        // john@example.com is a conductor, not a passenger
        $stmt = $this->pdo->prepare('
            SELECT p.passengers_id
            FROM passengers p
            JOIN employees e ON p.employees_id = e.employees_id
            JOIN users u ON e.users_id = u.users_id
            WHERE u.user_email = ?
        ');
        $stmt->execute(['john@example.com']);
        $passenger = $stmt->fetch();
        $this->assertFalse($passenger);
    }

    public function testReservationCountQuery(): void
    {
        $stmt = $this->pdo->query('SELECT journeys_id, COUNT(*) AS cnt FROM ASSO4 GROUP BY journeys_id');
        $rows = $stmt->fetchAll();
        $this->assertIsArray($rows);
        // Bob is a passenger on both journeys per init.sql
        $this->assertGreaterThanOrEqual(1, count($rows));
    }

    public function testReservationPageFileHasNoDoctype(): void
    {
        $content = file_get_contents(__DIR__ . '/../public/Reservation.php');
        $this->assertStringNotContainsString('<!DOCTYPE', $content);
        $this->assertStringNotContainsString('<html', $content);
        $this->assertStringNotContainsString('<head>', $content);
        $this->assertStringNotContainsString('<body>', $content);
    }

    public function testHomePageFileHasNoDoctype(): void
    {
        $content = file_get_contents(__DIR__ . '/../public/home.php');
        $this->assertStringNotContainsString('<!DOCTYPE', $content);
        $this->assertStringNotContainsString('<html', $content);
        $this->assertStringNotContainsString('<head>', $content);
    }
}
