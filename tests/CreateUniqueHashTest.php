<?php
declare(strict_types=1);

namespace Tests;

use app\logic\PasteLogic;
use flight\Engine;
use PHPUnit\Framework\TestCase;

class CreateUniqueHashTest extends TestCase
{
    private function createLogic(): PasteLogic
    {
        return new PasteLogic(new Engine(), [
            'encryption_key' => '',
            'enable_smtp' => false,
        ]);
    }

    public function testDefaultLengthIsEight(): void
    {
        $logic = $this->createLogic();
        $uid = $logic->createUniqueHash();

        $this->assertSame(8, strlen($uid));
    }

    public function testUidIsAlphanumeric(): void
    {
        $logic = $this->createLogic();

        for ($i = 0; $i < 20; $i++) {
            $uid = $logic->createUniqueHash();
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{8}$/', $uid);
        }
    }

    public function testRespectsCustomLength(): void
    {
        $logic = $this->createLogic();
        $uid = $logic->createUniqueHash(12);

        $this->assertSame(12, strlen($uid));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{12}$/', $uid);
    }
}