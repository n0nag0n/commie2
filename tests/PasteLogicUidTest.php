<?php
declare(strict_types=1);

namespace Tests;

use app\logic\PasteLogic;
use Defuse\Crypto\Key;
use Exception;
use flight\Engine;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PasteLogicUidTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/commie2-test-' . uniqid('', true) . '/';
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path . '/');
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function createLogic(): PasteLogic
    {
        $logic = new PasteLogic(new Engine(), [
            'encryption_key' => Key::createNewRandomKey()->saveToAsciiSafeString(),
            'enable_smtp' => false,
        ]);

        $reflection = new ReflectionClass($logic);
        $savedir = $reflection->getProperty('savedir');
        $savedir->setAccessible(true);
        $savedir->setValue($logic, $this->tempDir);

        return $logic;
    }

    public function testValidUidFormatIsAccepted(): void
    {
        $logic = $this->createLogic();
        $uid = 'abc12345';
        $path = $logic->getDataFilePath($uid, 'paste');

        $this->assertStringStartsWith($this->tempDir, $path);
        $this->assertStringEndsWith('abc12345.paste', $path);
    }

    public function testPathTraversalUidThrowsSameErrorAsMissingPaste(): void
    {
        $logic = $this->createLogic();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to find paste');

        $logic->getFileContentsAsObject('../config');
    }

    public function testSlashInUidThrowsUnableToFindPaste(): void
    {
        $logic = $this->createLogic();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to find paste');

        $logic->getFileContentsAsObject('abc/def');
    }

    public function testResolvedPathStaysInsideDataDirectory(): void
    {
        $logic = $this->createLogic();
        $uid = 'testuid1';
        $dir = $this->tempDir . 't/';
        mkdir($dir, 0755, true);

        $reflection = new ReflectionClass($logic);
        $method = $reflection->getMethod('assertValidUidAndPath');
        $method->setAccessible(true);

        $path = $logic->getDataFilePath($uid, 'paste');
        $method->invoke($logic, $uid, $path);

        $this->assertStringStartsWith(realpath($this->tempDir), realpath(dirname($path)));
    }
}