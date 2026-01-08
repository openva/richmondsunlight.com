<?php

/**
 * Generates social media preview card images for bills.
 *
 * Creates 1200x630 pixel images with:
 * - Faded bill PDF background (top ~25% at 80% opacity)
 * - Patron's photo in the bottom-right corner
 * - Richmond Sunlight branding in the bottom-left
 */
class BillPreviewImage
{
    /**
     * Image dimensions (Open Graph standard).
     */
    const WIDTH = 1200;
    const HEIGHT = 630;

    /**
     * Patron photo dimensions and position.
     */
    const PATRON_SIZE = 300;
    const PATRON_MARGIN_RIGHT = 120;
    const PATRON_MARGIN_BOTTOM = 30;

    /**
     * Logo dimensions.
     */
    const LOGO_HEIGHT = 80;
    const LOGO_MARGIN = 20;

    /**
     * @var array Bill data from API.
     */
    protected $bill;

    /**
     * @var string Cache directory path.
     */
    protected $cache_dir;

    /**
     * @param array $bill Bill data array from the API.
     */
    public function __construct(array $bill)
    {
        $this->bill = $bill;
        $this->cache_dir = $this->resolveCacheDir();
    }

    /**
     * Resolve a usable cache directory.
     *
     * Falls back to system temp directory if the configured CACHE_DIR
     * doesn't exist and can't be created.
     *
     * @return string
     */
    protected function resolveCacheDir(): string
    {
        $cache_dir = defined('CACHE_DIR') ? CACHE_DIR : sys_get_temp_dir();

        // If the directory exists, use it
        if (is_dir($cache_dir) && is_writable($cache_dir)) {
            return rtrim($cache_dir, '/');
        }

        // Try to create the directory
        if (@mkdir($cache_dir, 0755, true) && is_writable($cache_dir)) {
            return rtrim($cache_dir, '/');
        }

        // Fall back to system temp directory
        return rtrim(sys_get_temp_dir(), '/');
    }

    /**
     * Get the file path for the cached image.
     *
     * @return string
     */
    public function getCachePath(): string
    {
        $bill_number = preg_replace('/[^a-z0-9]/i', '', $this->bill['number']);
        return $this->cache_dir . '/preview-' . $this->bill['year'] . '-'
            . mb_strtolower($bill_number) . '.png';
    }

    /**
     * Check if a valid cached image exists.
     *
     * Cache for 7 days for current session, 30 days for older bills.
     *
     * @return bool
     */
    public function isCached(): bool
    {
        $path = $this->getCachePath();
        if (!file_exists($path)) {
            return false;
        }

        $is_current_session = isset($this->bill['session_id'])
            && defined('SESSION_ID')
            && $this->bill['session_id'] == SESSION_ID;

        $max_age = $is_current_session
            ? (60 * 60 * 24 * 7)   // 7 days for current session
            : (60 * 60 * 24 * 30); // 30 days for old bills

        return (filemtime($path) + $max_age) > time();
    }

    /**
     * Get the PDF URL for this bill.
     *
     * @return string|null
     */
    protected function getPdfUrl(): ?string
    {
        // For 2025+ bills, try the API-provided PDF URL first
        if ($this->bill['year'] >= 2025) {
            if (isset($this->bill['text']) && is_array($this->bill['text'])) {
                // Get the newest version with a PDF URL
                for ($i = count($this->bill['text']) - 1; $i >= 0; $i--) {
                    $text = (array) $this->bill['text'][$i];
                    if (!empty($text['pdf_url'])) {
                        return $text['pdf_url'];
                    }
                }
            }
            // If no API PDF URL, fall through to legacy URL
        }

        // Legacy URL (works for all bills with session_lis_id)
        if (empty($this->bill['session_lis_id'])) {
            return null;
        }

        return 'https://legacylis.virginia.gov/cgi-bin/legp604.exe?'
            . $this->bill['session_lis_id'] . '+ful+'
            . mb_strtoupper($this->bill['number']) . '+pdf';
    }

    /**
     * Download PDF and convert first page to image.
     *
     * @return resource|null GD image resource or null on failure.
     */
    protected function getPdfFirstPage()
    {
        $pdf_url = $this->getPdfUrl();
        if ($pdf_url === null) {
            return null;
        }

        // Download PDF to temp file
        $temp_pdf = sys_get_temp_dir() . '/bill-' . uniqid() . '.pdf';
        $temp_png = sys_get_temp_dir() . '/bill-' . uniqid() . '.png';

        try {
            $pdf_content = $this->fetchUrl($pdf_url);
            if ($pdf_content === false) {
                return null;
            }

            file_put_contents($temp_pdf, $pdf_content);

            // Convert first page to PNG using ImageMagick
            // -density 150 gives decent quality, [0] selects first page
            $cmd = sprintf(
                'convert -density 150 -limit memory 256MB -limit map 512MB %s[0] -resize %dx -quality 85 %s 2>&1',
                escapeshellarg($temp_pdf),
                self::WIDTH,
                escapeshellarg($temp_png)
            );

            exec($cmd, $output, $return_code);

            if ($return_code !== 0 || !file_exists($temp_png)) {
                @unlink($temp_pdf);
                @unlink($temp_png);
                return null;
            }

            $image = imagecreatefrompng($temp_png);

            // Clean up temp files after loading the image
            @unlink($temp_pdf);
            @unlink($temp_png);

            return $image;

        } catch (Exception $e) {
            error_log("BillPreviewImage: Exception: " . $e->getMessage());
            // Clean up on exception
            @unlink($temp_pdf);
            @unlink($temp_png);
            return null;
        }
    }

    /**
     * Fetch content from a URL.
     *
     * @param string $url
     * @param int    $timeout
     * @return string|false
     */
    protected function fetchUrl(string $url, int $timeout = 30)
    {
        if (function_exists('get_content')) {
            return get_content($url, $timeout);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'RichmondSunlight/1.0');
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    /**
     * Get the patron's photo as a GD image resource.
     *
     * @return resource|null
     */
    protected function getPatronPhoto()
    {
        if (empty($this->bill['patron_shortname'])) {
            return null;
        }

        $photo_path = $_SERVER['DOCUMENT_ROOT'] . '/images/legislators/large/'
            . $this->bill['patron_shortname'] . '.jpg';

        if (!file_exists($photo_path)) {
            return null;
        }

        return imagecreatefromjpeg($photo_path);
    }

    /**
     * Get the Richmond Sunlight logo.
     *
     * @return resource|null
     */
    protected function getLogo()
    {
        $logo_path = $_SERVER['DOCUMENT_ROOT']
            . '/images/templates/new/richmond-sunlight-logo.png';

        if (!file_exists($logo_path)) {
            return null;
        }

        return imagecreatefrompng($logo_path);
    }

    /**
     * Generate the preview image.
     *
     * @return string|null Path to the generated image, or null on failure.
     */
    public function generate(): ?string
    {
        // Create the base canvas
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        // Enable alpha blending
        imagealphablending($image, true);
        imagesavealpha($image, true);

        // Fill with white background
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $white);

        // Add PDF background (top ~25%)
        $pdf_image = $this->getPdfFirstPage();
        if ($pdf_image !== null) {
            $this->overlayPdfBackground($image, $pdf_image);
            imagedestroy($pdf_image);
        } else {
            // Fallback: light gray gradient background
            $this->addFallbackBackground($image);
        }

        // Add patron photo (bottom-right corner)
        $patron_photo = $this->getPatronPhoto();
        if ($patron_photo !== null) {
            $this->overlayPatronPhoto($image, $patron_photo);
            imagedestroy($patron_photo);
        }

        // Add Richmond Sunlight logo (bottom-left corner)
        $logo = $this->getLogo();
        if ($logo !== null) {
            $this->overlayLogo($image, $logo);
            imagedestroy($logo);
        }

        // Save to cache
        $cache_path = $this->getCachePath();
        imagepng($image, $cache_path, 6); // compression level 6
        imagedestroy($image);

        return file_exists($cache_path) ? $cache_path : null;
    }

    /**
     * Overlay the PDF first page as a faded background.
     *
     * @param resource $canvas    The main image canvas.
     * @param resource $pdf_image The PDF first page image.
     */
    protected function overlayPdfBackground($canvas, $pdf_image): void
    {
        $pdf_width = imagesx($pdf_image);
        $pdf_height = imagesy($pdf_image);

        // Scale to fill width
        $scale = self::WIDTH / $pdf_width;
        $scaled_height = $pdf_height * $scale;

        // Offset: start 180 pixels down in the PDF
        $pdf_y_offset = 180;

        // Use as much of the PDF as fits in the canvas height (accounting for offset)
        $use_height = min($scaled_height - $pdf_y_offset, self::HEIGHT);

        // Create temporary image for the PDF portion
        $temp = imagecreatetruecolor(self::WIDTH, (int)$use_height);

        // Fill with white first
        $white = imagecolorallocate($temp, 255, 255, 255);
        imagefilledrectangle($temp, 0, 0, self::WIDTH, (int)$use_height, $white);

        // Resample PDF to temp image, starting 200 pixels down in the source
        imagecopyresampled(
            $temp, $pdf_image,
            0, 0,                                              // dest x, y
            0, (int)($pdf_y_offset / $scale),                  // src x, y (offset in original PDF coords)
            self::WIDTH, (int)$use_height,                     // dest w, h
            $pdf_width, (int)($use_height / $scale)            // src w, h
        );

        // Copy the temp image to the canvas at 80% opacity for visibility
        imagecopymerge($canvas, $temp, 0, 0, 0, 0, self::WIDTH, (int)$use_height, 80);

        // Add gradient fade to white (150 pixels), starting 200 pixels from bottom
        // This leaves 50 pixels of solid white at the very bottom
        $fade_height = 150;
        $white_margin = 50;
        $fade_start = self::HEIGHT - $fade_height - $white_margin;
        $fade_end = self::HEIGHT - $white_margin;

        // Draw the gradient fade
        for ($y = $fade_start; $y < $fade_end; $y++) {
            $progress = ($y - $fade_start) / $fade_height;
            $alpha = (int)(127 * $progress); // 0 = opaque, 127 = transparent
            $fade_color = imagecolorallocatealpha($canvas, 255, 255, 255, 127 - $alpha);
            imageline($canvas, 0, $y, self::WIDTH, $y, $fade_color);
        }

        // Draw solid white for the bottom margin
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, $fade_end, self::WIDTH, self::HEIGHT, $white);

        imagedestroy($temp);
    }

    /**
     * Add a fallback gradient background if PDF is unavailable.
     *
     * @param resource $canvas
     */
    protected function addFallbackBackground($canvas): void
    {
        // Light gradient from top
        for ($y = 0; $y < self::HEIGHT / 3; $y++) {
            $factor = 1 - ($y / (self::HEIGHT / 3)) * 0.1;
            $gray = (int)(245 * $factor);
            $color = imagecolorallocate($canvas, $gray, $gray, $gray);
            imageline($canvas, 0, $y, self::WIDTH, $y, $color);
        }
    }

    /**
     * Overlay the patron's photo in the bottom-right corner.
     *
     * @param resource $canvas
     * @param resource $photo
     */
    protected function overlayPatronPhoto($canvas, $photo): void
    {
        $photo_width = imagesx($photo);
        $photo_height = imagesy($photo);

        // Scale to fit PATRON_SIZE
        $scale = min(self::PATRON_SIZE / $photo_width, self::PATRON_SIZE / $photo_height);
        $new_width = (int)($photo_width * $scale);
        $new_height = (int)($photo_height * $scale);

        // Position in bottom-right
        $x = self::WIDTH - $new_width - self::PATRON_MARGIN_RIGHT;
        $y = self::HEIGHT - $new_height - self::PATRON_MARGIN_BOTTOM;

        // Add a subtle white border
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle(
            $canvas,
            $x - 4, $y - 4,
            $x + $new_width + 4, $y + $new_height + 4,
            $white
        );

        // Add shadow outline
        $shadow = imagecolorallocate($canvas, 200, 200, 200);
        imagerectangle(
            $canvas,
            $x - 5, $y - 5,
            $x + $new_width + 5, $y + $new_height + 5,
            $shadow
        );

        imagecopyresampled(
            $canvas, $photo,
            $x, $y,
            0, 0,
            $new_width, $new_height,
            $photo_width, $photo_height
        );
    }

    /**
     * Overlay the Richmond Sunlight logo in the bottom-left corner.
     *
     * @param resource $canvas
     * @param resource $logo
     */
    protected function overlayLogo($canvas, $logo): void
    {
        $logo_width = imagesx($logo);
        $logo_height = imagesy($logo);

        // Scale to fit LOGO_HEIGHT
        $scale = self::LOGO_HEIGHT / $logo_height;
        $new_width = (int)($logo_width * $scale);
        $new_height = (int)($logo_height * $scale);

        // Position in bottom-left
        $x = self::LOGO_MARGIN;
        $y = self::HEIGHT - $new_height - self::LOGO_MARGIN;

        // Handle PNG transparency
        imagealphablending($canvas, true);

        imagecopyresampled(
            $canvas, $logo,
            $x, $y,
            0, 0,
            $new_width, $new_height,
            $logo_width, $logo_height
        );
    }

    /**
     * Get the public URL for this preview image.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return 'https://www.richmondsunlight.com/bill/' . $this->bill['year'] . '/'
            . mb_strtolower($this->bill['number']) . '/preview.png';
    }
}
