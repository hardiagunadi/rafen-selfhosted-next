<?php

namespace App\Services;

class MapCoverageTileService
{
    /**
     * @return array<int, string>
     */
    public function buildCoverageTileUrls(
        float $centerLatitude,
        float $centerLongitude,
        float $radiusKm,
        int $minZoom,
        int $maxZoom,
        int $maxTiles = 1400
    ): array {
        $minZoom = max(1, min($minZoom, 19));
        $maxZoom = max($minZoom, min($maxZoom, 19));
        $radiusKm = max(0.2, min($radiusKm, 50));

        $latitudeDelta = $radiusKm / 111.32;
        $longitudeDelta = $radiusKm / max(0.1, 111.32 * cos(deg2rad($centerLatitude)));

        $north = min(85.05112878, $centerLatitude + $latitudeDelta);
        $south = max(-85.05112878, $centerLatitude - $latitudeDelta);
        $west = max(-180, $centerLongitude - $longitudeDelta);
        $east = min(180, $centerLongitude + $longitudeDelta);

        $urls = [];

        for ($zoom = $minZoom; $zoom <= $maxZoom; $zoom++) {
            [$northWestX, $northWestY] = $this->latLngToTile($north, $west, $zoom);
            [$southEastX, $southEastY] = $this->latLngToTile($south, $east, $zoom);

            $minTileX = min($northWestX, $southEastX);
            $maxTileX = max($northWestX, $southEastX);
            $minTileY = min($northWestY, $southEastY);
            $maxTileY = max($northWestY, $southEastY);

            for ($tileX = $minTileX; $tileX <= $maxTileX; $tileX++) {
                for ($tileY = $minTileY; $tileY <= $maxTileY; $tileY++) {
                    $urls[] = "https://tile.openstreetmap.org/{$zoom}/{$tileX}/{$tileY}.png";
                    $urls[] = "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{$zoom}/{$tileY}/{$tileX}";

                    if (count($urls) >= $maxTiles) {
                        return array_values(array_unique($urls));
                    }
                }
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function latLngToTile(float $latitude, float $longitude, int $zoom): array
    {
        $latitude = max(-85.05112878, min(85.05112878, $latitude));
        $longitude = max(-180, min(180, $longitude));

        $tileCount = 2 ** $zoom;
        $x = (int) floor((($longitude + 180) / 360) * $tileCount);

        $latitudeRadians = deg2rad($latitude);
        $mercator = log(tan((M_PI / 4) + ($latitudeRadians / 2)));
        $y = (int) floor((1 - ($mercator / M_PI)) / 2 * $tileCount);

        $x = max(0, min($x, $tileCount - 1));
        $y = max(0, min($y, $tileCount - 1));

        return [$x, $y];
    }
}
