<?php declare(strict_types=1);

/**
 * Convert longitude and latitude to locator
 * @param float $lon Longitude
 * @param float $lat Latitude
 * @param int $precision (optional) 4, 6, 8, 10. All other values throw an exception
 * @throws \Exception if $precision is anything other than 4, 6, 8, or 10
 * @todo Implement $precision
 * @return string Locator in the given precision
 */
function lonLat2Locator(float $lon, float $lat, int $precision = 6): string {
    if (!in_array($precision, array(4, 6, 8, 10))) {
        throw new \Exception('Wrong precision value');
    }
    $lon = $lon + 180;
    $fieldIndexLon = (int) ($lon / 20 + 65);
    $lon = fmod($lon, 20);
    $squareIndexLon = (int) ($lon / 2);
    $lat = $lat + 90;
    $fieldIndexLat = (int) ($lat / 10 + 65);
    $lat = fmod($lat, 10);
    $squareIndexLat = (int) ($lat / 1);

    $ret = chr($fieldIndexLon) .
        chr($fieldIndexLat) .
        $squareIndexLon .
        $squareIndexLat;

    if ($precision < 6) {
        return $ret;
    }

    $subSquareIndexLon = (int) (fmod($lon, 2) / 0.083333 + 65);
    $subSquareIndexLat = (int) (fmod($lat, 1) / 0.0416665 + 65);
    $ret .= chr($subSquareIndexLon) .
        chr($subSquareIndexLat);

    if ($precision < 8) {
        return $ret;
    }

    throw new \Exception('Not yet implemented');
}

/**
 * Parses a locator to longitude and latitude (center of square)
 * @param string $locator Locator to parse
 * @param int $precision (optional, reference) precision of $locator
 * @throws \Exception if $precision is anything other than 4, 6, 8, or 10
 * @return array Array with the indexes "lon" and "lat" of type float
 */
function locator2LonLat(string $locator, int $precision = 0): array {
    $locator = strtoupper($locator);
    $precision = strlen($locator);
    if (!in_array($precision, array(4, 6, 8, 10))) {
        throw new \Exception('Wrong precision value');
    }
    $fieldIndexLon = (int) ord(substr($locator, 0, 1)) - 65;
    $squareIndexLon = (int) substr($locator, 2, 1);
    $fieldIndexLat = (int) ord(substr($locator, 1, 1)) - 65;
    $squareIndexLat = (int) substr($locator, 3, 1);

    $ret = array('lon' => -180.0, 'lat' => -90.0);
    $ret['lon'] += $fieldIndexLon * 20;
    $ret['lon'] += $squareIndexLon * 2;
    $ret['lat'] += $fieldIndexLat * 10;
    $ret['lat'] += $squareIndexLat * 1;

    if ($precision < 6) {
        // @todo: middle of the square with precision 6
        return $ret;
    }
    $subSquareIndexLon = (int) ord(substr($locator, 4, 1)) - 65;
    $subSquareIndexLat = (int) ord(substr($locator, 5, 1)) - 65;
    $ret['lon'] += $subSquareIndexLon / 12;
    $ret['lat'] += $subSquareIndexLat / 24;

    if ($precision < 8) {
        // middle of the square with precision 6
        $ret['lon'] += 1 / 24;
        $ret['lat'] += 1 / 48;
        return $ret;
    }

    throw new \Exception('Not yet implemented');
}

/**
 * Fetches a JSON object from a URL
 * @param string $url URL to fetch data from
 * @param array $params (optional) Object to send as body
 * @param string $method (optional) Default uses GET if $body is NULL, POST otherwise
 * @param bool $followRedirects (optional) If set to false, redirects are not followed
 * @param bool $allowInsecure (optional) If set to true certificate errors are ignored
 * @return object Response
 * @throws \Exception if $method is unknown
 * @throws \Exception if URL cannot be reached (status code other than 200 after redirects)
 * @throws \Exception if certificate is insecure
 */
function getJson(
    string $url,
    object $body = null,
    string $method = 'AUTO',
    bool $followRedirects = true,
    bool $allowInsecure = false
): object {
    if ($method == 'AUTO') {
        if ($body) {
            $method = 'POST';
        } else {
            $method = 'GET';
        }
    }
    if (!in_array(strtoupper($method), array(
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'HEAD',
    ))) {
        throw new \Exception('Unknown method "' . $method . '"');
    }
    $headers = array(
        'User-Agent: CURL',
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($followRedirects) {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    }
    if ($allowInsecure) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }
    if ($body) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($ch);
    $parsedResult = json_decode($result);
    if (curl_errno($ch)) {
        throw new \Exception('CURL error: "' . curl_error($ch) . '"');
    }
    $info = curl_getinfo($ch);
    curl_close($ch);

    if (!$parsedResult) {
        throw new \Exception('Result is non-JSON: "' . $result . '"');
    }
    return (object) $parsedResult;
}
