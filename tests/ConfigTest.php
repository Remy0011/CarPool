<?php

declare(strict_types=1);

namespace CarPool\Tests;

use PDO;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testGetDBReturnsPDO(): void
    {
        $pdo = getDB();
        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function testGetDBIsSingleton(): void
    {
        $pdo1 = getDB();
        $pdo2 = getDB();
        $this->assertSame($pdo1, $pdo2);
    }

    public function testDatabaseHasUsersTable(): void
    {
        $pdo = getDB();
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        $this->assertNotEmpty($stmt->fetchAll());
    }

    public function testDatabaseHasJourneysTable(): void
    {
        $pdo = getDB();
        $stmt = $pdo->query("SHOW TABLES LIKE 'journeys'");
        $this->assertNotEmpty($stmt->fetchAll());
    }

    public function testDatabaseHasSeededUsers(): void
    {
        $pdo = getDB();
        $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM users');
        $count = $stmt->fetch()['cnt'];
        $this->assertGreaterThanOrEqual(3, $count);
    }
}
