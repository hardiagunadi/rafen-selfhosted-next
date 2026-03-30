<?php

namespace App\Services;

use App\Models\TenantSettings;
use Illuminate\Support\Facades\Storage;

class PwaIconService
{
    private const CACHE_VERSION = 'v2';

    public function appName(?TenantSettings $settings, string $fallback, string $context): string
    {
        return $this->appendContext($this->displayName($settings, $fallback), $context);
    }

    public function appShortName(?TenantSettings $settings, string $fallback, string $context): string
    {
        return $this->appendContext($this->shortName($settings, $fallback), $context);
    }

    public function displayName(?TenantSettings $settings, string $fallback): string
    {
        $businessName = trim((string) ($settings?->business_name ?? ''));
        $companyName = trim((string) ($settings?->user?->company_name ?? ''));
        $ownerName = trim((string) ($settings?->user?->name ?? ''));

        if ($businessName !== '') {
            return $businessName;
        }

        if ($companyName !== '') {
            return $companyName;
        }

        if ($ownerName !== '') {
            return $ownerName;
        }

        return $fallback;
    }

    public function shortName(?TenantSettings $settings, string $fallback): string
    {
        return $this->displayName($settings, $fallback);
    }

    /**
     * @param  array<string, mixed>  $routeParameters
     */
    public function iconUrl(?TenantSettings $settings, int $size, string $routeName, array $routeParameters = []): string
    {
        if ($this->hasCustomLogo($settings)) {
            return route($routeName, array_merge($routeParameters, ['size' => $size]));
        }

        return asset("branding/favicon-{$size}.png");
    }

    public function iconPath(?TenantSettings $settings, int $size): string
    {
        if (! $this->hasCustomLogo($settings)) {
            return public_path("branding/favicon-{$size}.png");
        }

        $disk = Storage::disk('public');
        $relativePath = $this->cachedIconRelativePath($settings, $size);

        if (! $disk->exists($relativePath)) {
            $disk->makeDirectory(dirname($relativePath));
            $this->generateIcon($settings, $size, $disk->path($relativePath));
        }

        return $disk->path($relativePath);
    }

    private function hasCustomLogo(?TenantSettings $settings): bool
    {
        return $settings !== null
            && ! empty($settings->business_logo)
            && Storage::disk('public')->exists($settings->business_logo);
    }

    private function cachedIconRelativePath(TenantSettings $settings, int $size): string
    {
        $signature = sha1(implode('|', [
            self::CACHE_VERSION,
            (string) $settings->user_id,
            (string) $settings->business_logo,
            (string) optional($settings->updated_at)?->timestamp,
            (string) $size,
        ]));

        return "pwa-icons/{$signature}-{$size}.png";
    }

    private function generateIcon(TenantSettings $settings, int $size, string $targetPath): void
    {
        $disk = Storage::disk('public');
        $sourceBinary = $disk->get($settings->business_logo);
        $sourceImage = @imagecreatefromstring($sourceBinary);

        if ($sourceImage === false) {
            copy(public_path("branding/favicon-{$size}.png"), $targetPath);

            return;
        }

        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        $canvas = imagecreatetruecolor($size, $size);

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $backgroundColor = $this->resolveBackgroundColor($sourceImage, $width, $height);

        if ($backgroundColor === null) {
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);
        } else {
            $background = imagecolorallocate($canvas, $backgroundColor['red'], $backgroundColor['green'], $backgroundColor['blue']);
            imagefill($canvas, 0, 0, $background);
        }

        $safeZone = (int) round($size * 0.62);
        $scale = min($safeZone / max($width, 1), $safeZone / max($height, 1));

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $targetX = (int) floor(($size - $targetWidth) / 2);
        $targetY = (int) floor(($size - $targetHeight) / 2);

        imagecopyresampled(
            $canvas,
            $sourceImage,
            $targetX,
            $targetY,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height
        );

        imagepng($canvas, $targetPath, 6);

        imagedestroy($sourceImage);
        imagedestroy($canvas);
    }

    /**
     * @return array{red:int,green:int,blue:int}|null
     */
    private function resolveBackgroundColor(\GdImage $sourceImage, int $width, int $height): ?array
    {
        $transparentPixels = 0;
        $visiblePixels = 0;
        $luminanceTotal = 0.0;
        $sampleStep = max(1, (int) floor(max($width, $height) / 72));

        for ($y = 0; $y < $height; $y += $sampleStep) {
            for ($x = 0; $x < $width; $x += $sampleStep) {
                $rgba = imagecolorsforindex($sourceImage, imagecolorat($sourceImage, $x, $y));
                $alpha = (int) ($rgba['alpha'] ?? 0);

                if ($alpha >= 110) {
                    $transparentPixels++;

                    continue;
                }

                $visiblePixels++;
                $luminanceTotal += (0.2126 * $rgba['red']) + (0.7152 * $rgba['green']) + (0.0722 * $rgba['blue']);
            }
        }

        $sampleCount = $transparentPixels + $visiblePixels;

        if ($sampleCount === 0 || ($transparentPixels / $sampleCount) < 0.08) {
            return null;
        }

        if ($visiblePixels === 0) {
            return [
                'red' => 19,
                'green' => 103,
                'blue' => 164,
            ];
        }

        $averageLuminance = $luminanceTotal / $visiblePixels;

        if ($averageLuminance >= 160) {
            return [
                'red' => 19,
                'green' => 103,
                'blue' => 164,
            ];
        }

        return [
            'red' => 244,
            'green' => 247,
            'blue' => 251,
        ];
    }

    private function appendContext(string $name, string $context): string
    {
        $normalizedName = trim($name);
        $normalizedContext = trim($context);

        if ($normalizedName === '' || $normalizedContext === '') {
            return $normalizedName;
        }

        if (preg_match('/\b'.preg_quote($normalizedContext, '/').'\b/i', $normalizedName) === 1) {
            return $normalizedName;
        }

        return $normalizedName.' '.$normalizedContext;
    }
}
