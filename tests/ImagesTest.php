<?php
/**
 * SB-Tech — optimize_image() (functions/images.php).
 *
 * Builds tiny images with GD in a temp dir; no DB needed (TEST-05).
 */

use PHPUnit\Framework\TestCase;

class ImagesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available.');
        }
        $this->dir = sys_get_temp_dir() . '/sbtech_img_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir) && is_dir($this->dir)) {
            array_map('unlink', glob($this->dir . '/*') ?: []);
            rmdir($this->dir);
        }
    }

    private function makeImage(string $name, int $w, int $h, string $kind): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 30, 120, 200));
        $path = $this->dir . '/' . $name;
        $kind === 'png' ? imagepng($im, $path) : imagejpeg($im, $path, 90);
        return $path;
    }

    public function test_large_jpeg_is_shrunk_to_max_dimension_keeping_ratio(): void
    {
        $path = $this->makeImage('big.jpg', 400, 200, 'jpg');
        $r = optimize_image($path, 100);
        $this->assertTrue($r['ok']);
        $this->assertSame([100, 50], [$r['width'], $r['height']]);
        $info = getimagesize($path);
        $this->assertSame([100, 50, IMAGETYPE_JPEG], [$info[0], $info[1], $info[2]]);
    }

    public function test_small_png_keeps_size_and_format(): void
    {
        $path = $this->makeImage('small.png', 40, 30, 'png');
        $r = optimize_image($path, 2000);
        $this->assertTrue($r['ok']);
        $this->assertSame('image/png', $r['mime']);
        $this->assertSame([40, 30], [$r['width'], $r['height']]);
    }

    public function test_non_image_with_image_extension_is_rejected(): void
    {
        $path = $this->dir . '/fake.jpg';
        file_put_contents($path, '<?php echo "not an image"; ?>');
        $r = optimize_image($path);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('not a valid', $r['message']);
    }

    public function test_missing_file_is_rejected(): void
    {
        $this->assertFalse(optimize_image($this->dir . '/nope.png')['ok']);
    }
}
