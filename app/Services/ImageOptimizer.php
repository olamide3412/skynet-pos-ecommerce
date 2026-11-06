<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class ImageOptimizer
{
    protected ?ImageManager $manager = null;

    public function __construct()
    {
        if (extension_loaded('gd')) {
            try {
                $this->manager = new ImageManager(new Driver());
            } catch (Throwable $e) {
                $this->manager = null;
            }
        }
    }

    /**
     * Store and optionally compress an uploaded image.
     *
     * @param UploadedFile $file The uploaded file
     * @param string $directory Directory inside public disk (e.g. 'products')
     * @param bool $compress Whether compression & WebP optimization is enabled
     * @param string|null $slug Descriptive SEO slug base for the filename
     * @param int $maxWidth Max width in pixels (default 1200)
     * @param int $maxHeight Max height in pixels (default 1200)
     * @param int $quality Compression quality 1-100 (default 82)
     * @return string Stored relative file path (e.g. 'products/sony-wh-1000xm5-a3f89c.webp')
     */
    public function store(
        UploadedFile $file,
        string $directory = 'products',
        bool $compress = true,
        ?string $slug = null,
        int $maxWidth = 1200,
        int $maxHeight = 1200,
        int $quality = 82
    ): string {
        $baseName = $slug ? Str::slug($slug) : null;
        $suffix = strtolower(Str::random(6));

        // If compression is toggled off or GD is unavailable, store standard file with SEO name
        if (!$compress || !$this->manager) {
            if ($baseName) {
                $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
                $filename = "{$baseName}-{$suffix}.{$ext}";
                return $file->storeAs($directory, $filename, 'public');
            }
            return $file->store($directory, 'public');
        }

        try {
            $image = $this->manager->read($file->getRealPath());

            // Scale down preserving aspect ratio if dimensions exceed maximum
            $image->scaleDown(width: $maxWidth, height: $maxHeight);

            // Encode to modern WebP for best compression & page speed
            $encoded = (string) $image->toWebp(quality: $quality);

            $filename = ($baseName ? "{$baseName}-{$suffix}" : Str::random(40)) . '.webp';
            $path = trim($directory, '/') . '/' . $filename;

            Storage::disk('public')->put($path, $encoded);

            return $path;
        } catch (Throwable $e) {
            Log::warning("Image compression failed for {$file->getClientOriginalName()}, falling back to original upload: " . $e->getMessage());
            if ($baseName) {
                $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
                $filename = "{$baseName}-{$suffix}.{$ext}";
                return $file->storeAs($directory, $filename, 'public');
            }
            return $file->store($directory, 'public');
        }
    }
}

