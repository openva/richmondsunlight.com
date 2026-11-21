<?php

use PHPUnit\Framework\TestCase;

/**
 * Import::fetch_photo(), which rs-machine uses to save legislators' photos. The House serves some
 * as WebP, which are converted to JPEGs, so that every photo has a suffix that says its type.
 *
 * The "URLs" here are local files, which file_get_contents() reads the same way.
 */
class ImportFetchPhotoTest extends TestCase
{
    private string $previousDirectory;
    private string $directory;

    protected function setUp(): void
    {
        if (!function_exists('imagewebp') || !function_exists('imagecreatefromwebp')) {
            $this->markTestSkipped('PHP\'s GD extension, with WebP support, isn\'t installed.');
        }

        // fetch_photo() saves to photos/, in the current directory.
        $this->previousDirectory = getcwd();
        $this->directory = sys_get_temp_dir() . '/fetch-photo-' . bin2hex(random_bytes(4));
        mkdir($this->directory . '/source', 0755, true);
        chdir($this->directory);
    }

    protected function tearDown(): void
    {
        if (!isset($this->directory)) {
            return;
        }
        chdir($this->previousDirectory);
        foreach (glob($this->directory . '/{source,photos}/*', GLOB_BRACE) as $file) {
            unlink($file);
        }
        foreach (['/source', '/photos', ''] as $subdirectory) {
            if (is_dir($this->directory . $subdirectory)) {
                rmdir($this->directory . $subdirectory);
            }
        }
    }

    public function testAWebpPhotoIsSavedAsAJpeg(): void
    {
        $source = $this->image('imagewebp', 'photo.webp');

        $saved = $this->import()->fetch_photo($source, 'kmcpike');

        $this->assertSame('photos/kmcpike.jpg', $saved);
        $this->assertSame('image/jpeg', mime_content_type($saved));
        $this->assertSame([40, 30], array_slice(getimagesize($saved), 0, 2));
        $this->assertFileDoesNotExist('photos/kmcpike', 'The WebP original is left behind.');
    }

    public function testAGifPhotoIsSavedAsAJpeg(): void
    {
        $saved = $this->import()->fetch_photo($this->image('imagegif', 'photo.gif'), 'jdoe');

        $this->assertSame('photos/jdoe.jpg', $saved);
        $this->assertSame('image/jpeg', mime_content_type($saved));
    }

    /**
     * JPEGs have no transparency. Transparent parts become white, as they'd look on the page,
     * rather than black.
     */
    public function testTransparencyBecomesWhite(): void
    {
        $image = imagecreatetruecolor(10, 10);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        $source = $this->directory . '/source/transparent.webp';
        imagewebp($image, $source, 100);

        $saved = $this->import()->fetch_photo($source, 'clear');

        $pixel = imagecolorsforindex($jpeg = imagecreatefromjpeg($saved), imagecolorat($jpeg, 5, 5));
        $this->assertGreaterThan(245, min($pixel['red'], $pixel['green'], $pixel['blue']));
    }

    public function testJpegsAndPngsAreKeptAsTheyAre(): void
    {
        $jpeg = $this->image('imagejpeg', 'photo.jpg');
        $png = $this->image('imagepng', 'photo.png');

        $this->assertSame('photos/a.jpg', $this->import()->fetch_photo($jpeg, 'a'));
        $this->assertFileEquals($jpeg, 'photos/a.jpg');
        $this->assertSame('photos/b.png', $this->import()->fetch_photo($png, 'b'));
        $this->assertFileEquals($png, 'photos/b.png');
    }

    /**
     * What isn't a photo isn't saved, rather than saved with no suffix.
     */
    public function testWhatIsntAPhotoIsDiscarded(): void
    {
        $html = $this->directory . '/source/missing.html';
        file_put_contents($html, '<!DOCTYPE html><html><body>Not found</body></html>');
        $text = $this->directory . '/source/notes.txt';
        file_put_contents($text, 'Just some text.');

        $this->assertFalse($this->import()->fetch_photo($html, 'nobody'));
        $this->assertFalse($this->import()->fetch_photo($text, 'nothing'));
        $this->assertSame([], glob('photos/*'));
    }

    private function image(string $writer, string $name): string
    {
        $image = imagecreatetruecolor(40, 30);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 60, 30));
        $path = $this->directory . '/source/' . $name;
        $writer($image, $path);
        return $path;
    }

    private function import(): Import
    {
        return new Import(new class extends Log {
            public function put($message, $level = 3)
            {
                return true;
            }
        });
    }
}
