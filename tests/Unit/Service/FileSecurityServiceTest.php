<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\FileSecurityService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use PHPUnit\Framework\TestCase;

class FileSecurityServiceTest extends TestCase
{
    private string $uploadDir;

    protected function setUp(): void
    {
        $this->uploadDir = sys_get_temp_dir() . '/uploads-' . uniqid();
        mkdir($this->uploadDir, 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->uploadDir . '/*') ?: []);
        @rmdir($this->uploadDir);
    }

    public function testValidImagePassesValidation(): void
    {
        $service = new FileSecurityService($this->uploadDir);
        $filename = $service->saveUploadedFile($this->createImageUpload('foto.jpg'));

        $this->assertMatchesRegularExpression('/^foto-[0-9a-f]{8}\.jpg$/', $filename);
        $this->assertFileExists($this->uploadDir . '/' . $filename);
    }

    public function testOversizedFileIsRejected(): void
    {
        $service = new FileSecurityService($this->uploadDir);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Limit');

        $service->validateUploadedFile($this->createMockedUpload(
            'big.jpg',
            'image/jpeg',
            10 * 1024 * 1024,
            'image.jpg'
        ));
    }

    public function testDisallowedMimeTypeIsRejected(): void
    {
        $service = new FileSecurityService($this->uploadDir);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('nicht erlaubt');

        $service->validateUploadedFile($this->createMockedUpload(
            'evil.pdf',
            'application/pdf',
            1024,
            'image.jpg'
        ));
    }

    public function testMimeMismatchIsRejected(): void
    {
        $service = new FileSecurityService($this->uploadDir);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Manipulation');

        $service->validateUploadedFile($this->createMockedUpload(
            'fake.jpg',
            'image/jpeg',
            1024,
            'textfile.jpg',
            false
        ));
    }

    public function testTooSmallImageIsRejected(): void
    {
        $service = new FileSecurityService($this->uploadDir);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('mindestens');

        $service->validateUploadedFile($this->createImageUpload('small.jpg', 'small.jpg', 'image/jpeg', 50, 50));
    }

    public function testMultipleExtensionsAreRejected(): void
    {
        $service = new FileSecurityService($this->uploadDir);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('mehreren Extensions');

        $service->validateUploadedFile($this->createImageUpload('shell.php.jpg'));
    }

    public function testNonImageContentIsRejected(): void
    {
        $service = new FileSecurityService($this->uploadDir);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('nicht erlaubt');

        $path = $this->uploadDir . '/not-an-image.png';
        file_put_contents($path, str_repeat('A', 2000));
        // Ohne Client-MIME rät Symfony selbst: text/plain -> abgelehnt
        $file = new UploadedFile($path, 'not-an-image.png', null, null, true);

        $service->validateUploadedFile($file);
    }

    public function testSecureDeleteRemovesFile(): void
    {
        $service = new FileSecurityService($this->uploadDir);
        $filename = $service->saveUploadedFile($this->createImageUpload('foto.jpg'));

        $service->secureDelete($filename);

        $this->assertFileDoesNotExist($this->uploadDir . '/' . $filename);
    }

    public function testSecureDeleteIgnoresMissingFile(): void
    {
        $service = new FileSecurityService($this->uploadDir);

        $service->secureDelete('existiert-nicht.jpg');

        $this->addToAssertionCount(1);
    }

    public function testSecureDeleteRejectsPathTraversal(): void
    {
        $service = new FileSecurityService($this->uploadDir);

        // Datei liegt AUSSERHALB des Upload-Verzeichnisses
        $outside = dirname($this->uploadDir) . '/evil-' . uniqid() . '.jpg';
        file_put_contents($outside, 'payload');

        $this->expectException(\InvalidArgumentException::class);

        $service->secureDelete('../' . basename($outside));
    }

    /**
     * Erzeugt ein echtes 200x200-JPEG, damit finfo/getimagesize greifen.
     */
    private function createImageUpload(string $originalName, string $fileBase = 'image.jpg', ?string $clientMime = null, int $width = 200, int $height = 200): UploadedFile
    {
        $path = $this->uploadDir . '/' . $fileBase;
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 0, 0));
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return new UploadedFile($path, $originalName, $clientMime ?? 'image/jpeg', null, true);
    }

    private function createMockedUpload(string $originalName, string $mime, int $size, string $realFile, bool $imageContent = true): UploadedFile
    {
        $path = $this->uploadDir . '/' . $realFile;
        if ($imageContent) {
            $img = imagecreatetruecolor(200, 200);
            imagefill($img, 0, 0, imagecolorallocate($img, 0, 0, 255));
            imagejpeg($img, $path, 90);
            imagedestroy($img);
        } else {
            file_put_contents($path, str_repeat('kein bild ', 300));
        }

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn($originalName);
        $file->method('getMimeType')->willReturn($mime);
        $file->method('getSize')->willReturn($size);
        $file->method('getRealPath')->willReturn($path);

        return $file;
    }
}
