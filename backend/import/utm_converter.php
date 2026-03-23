<?php
/**
 * UTM to WGS84 coordinate converter
 * Input CRS:  EPSG:32651 (UTM Zone 51N)
 * Output CRS: EPSG:4326  (WGS84)
 *
 * Uses standard UTM inverse projection (Helmert / Karney-style).
 * No external libraries required.
 */

function utmToWgs84(float $easting, float $northing, int $zone = 51, bool $isNorthHemisphere = true): array
{
    // WGS84 ellipsoid constants
    $a        = 6378137.0;           // semi-major axis (m)
    $f        = 1 / 298.257223563;   // flattening
    $b        = $a * (1 - $f);       // semi-minor axis
    $e2       = 2 * $f - $f * $f;    // eccentricity squared
    $e_prime2 = $e2 / (1 - $e2);

    $k0 = 0.9996;       // scale factor
    $E0 = 500000.0;     // false easting (m)
    $N0 = $isNorthHemisphere ? 0.0 : 10000000.0; // false northing

    $x = $easting  - $E0;
    $y = $northing - $N0;

    // Central meridian for zone
    $lon0 = deg2rad(($zone - 1) * 6 - 180 + 3);

    $M  = $y / $k0;
    $mu = $M / ($a * (1 - $e2 / 4 - 3 * $e2 * $e2 / 64 - 5 * $e2 * $e2 * $e2 / 256));

    $e1 = (1 - sqrt(1 - $e2)) / (1 + sqrt(1 - $e2));

    $phi1 = $mu
        + (3 * $e1 / 2 - 27 * pow($e1, 3) / 32)         * sin(2 * $mu)
        + (21 * pow($e1, 2) / 16 - 55 * pow($e1, 4) / 32) * sin(4 * $mu)
        + (151 * pow($e1, 3) / 96)                         * sin(6 * $mu)
        + (1097 * pow($e1, 4) / 512)                       * sin(8 * $mu);

    $N1 = $a / sqrt(1 - $e2 * sin($phi1) ** 2);
    $T1 = tan($phi1) ** 2;
    $C1 = $e_prime2 * cos($phi1) ** 2;
    $R1 = $a * (1 - $e2) / pow(1 - $e2 * sin($phi1) ** 2, 1.5);
    $D  = $x / ($N1 * $k0);

    $lat = $phi1
        - ($N1 * tan($phi1) / $R1) * (
            $D ** 2 / 2
            - (5 + 3 * $T1 + 10 * $C1 - 4 * $C1 ** 2 - 9 * $e_prime2) * $D ** 4 / 24
            + (61 + 90 * $T1 + 298 * $C1 + 45 * $T1 ** 2 - 252 * $e_prime2 - 3 * $C1 ** 2)
              * $D ** 6 / 720
        );

    $lon = $lon0 + (
            $D
            - (1 + 2 * $T1 + $C1)                                                           * $D ** 3 / 6
            + (5 - 2 * $C1 + 28 * $T1 - 3 * $C1 ** 2 + 8 * $e_prime2 + 24 * $T1 ** 2)     * $D ** 5 / 120
        ) / cos($phi1);

    return [
        'latitude'  => rad2deg($lat),
        'longitude' => rad2deg($lon),
    ];
}

/**
 * Validate that UTM coordinates are plausibly in Zone 51N
 * (covers the Philippines / Polomolok / SOCCSKSARGEN area)
 */
function isValidUtm51N(float $easting, float $northing): bool
{
    return ($easting  >= 100000 && $easting  <= 900000)
        && ($northing >= 0      && $northing <= 10000000);
}
