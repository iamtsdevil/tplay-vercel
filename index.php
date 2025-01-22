<?php
header('Content-Security-Policy: default-src \'self\';');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/dash+xml');
header("Cache-Control: max-age=20, public");
header('Content-Disposition: attachment; filename="TsDevil_.mpd"');
// error_reporting(0);
// ini_set('display_errors', 0);

$userAgent = 'DevilBhai/5.0 AppleWebKit/534.46.0';
$proxy = "tcp://103.172.84.252:49155";
$proxyAuth = base64_encode("premi:pQA2G23yU6");

$beginTimestamp = isset($_GET['utc']) ? intval($_GET['utc']) : null;
$endTimestamp = isset($_GET['lutc']) ? intval($_GET['lutc']) : null;
$begin = $beginTimestamp ? date('Ymd\THis', $beginTimestamp) : 'unknown';
$end = $endTimestamp ? date('Ymd\THis', $endTimestamp) : 'unknown';
//$id = $_GET['id'] ?? exit;
$dashUrl = secure_values('decrypt', urldecode($_GET['turl']));
$hmac = secure_values('decrypt', urldecode($_GET['auth']));

//echo $dashUrl;
//echo $hmac;
//$dashUrl = 'https://bpaicatchupta7.akamaized.net/bpk-tv/irdeto_com_Channel_257/output/master.mpd';
//$hmac = 'hdntl=exp=1737642082~acl=%2fbpk-tv%2firdeto_com_Channel_257%2foutput%2f*~id=1076415189~data=hdntl~hmac=d316048942322a32e93b41b644efeec30ad11ce31df45f63aae5dda79e031864';

function secure_values($action, $data) {
    $protec = "";
    $method = 'AES-128-CBC';
    $ky = 'iamtsdevil';
    $iv = substr(sha1($ky.'coolapps'."24662b4f995b7b3d348211c94fdaa080"), 0, 16);
    
    if ($action == "encrypt") {
        $encrypted = openssl_encrypt($data, $method, $ky, OPENSSL_RAW_DATA, $iv);
        if (!empty($encrypted)) {
            $protec = bin2hex($encrypted);
        }
    } else {
        $decrypted = openssl_decrypt(hex2bin($data), $method, $ky, OPENSSL_RAW_DATA, $iv);
        if (!empty($decrypted)) {
            $protec = $decrypted;
        }
    }
    return $protec;
}

function createStreamContext($headers, $proxy, $proxyAuth) {
    return stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", array_merge($headers, ["Proxy-Authorization: Basic $proxyAuth"])),
            'proxy' => $proxy,
            'ignore_errors' => true,
            'request_fulluri' => true
        ]
    ]);
}

function fetchMPDManifest(string $url, string $userAgent, string $hmac, $proxy, $proxyAuth): ?string {
    $trueUrl = $url . '?' . $hmac;

    $headers = [
        "User-Agent: $userAgent",
        'Origin: https://watch.tataplay.com',
        'Referer: https://watch.tataplay.com/'
    ];

    $context = createStreamContext($headers, $proxy, $proxyAuth);
    $content = @file_get_contents($trueUrl, false, $context);
    //echo $content;
    return $content !== false ? $content : null;
}

function extractPsshFromManifest(string $content, string $baseUrl, string $userAgent, ?int $beginTimestamp, string $hmac, $proxy, $proxyAuth): ?array {
    if (($xml = @simplexml_load_string($content)) === false) return null;

    foreach ($xml->Period->AdaptationSet as $set) {
        if ((string)$set['contentType'] === 'audio') {
            foreach ($set->Representation as $rep) {
                $template = $rep->SegmentTemplate ?? null;
                if ($template) {
                    $startNumber = $beginTimestamp
                        ? (int)($template['startNumber'] ?? 0)
                        : (int)($template['startNumber'] ?? 0) + (int)($template->SegmentTimeline->S['r'] ?? 0);
                    
                    $media = str_replace(
                        ['$RepresentationID$', '$Number$'], 
                        [(string)$rep['id'], $startNumber], 
                        $template['media']
                    );
                    $trueUrl = "$baseUrl/dash/$media?" . $hmac;

                    $headers = [
                        "User-Agent: $userAgent",
                        'Origin: https://watch.tataplay.com',
                        'Referer: https://watch.tataplay.com/',
                    ];

                    $context = createStreamContext($headers, $proxy, $proxyAuth);
                    $content = @file_get_contents($trueUrl, false, $context);

                    if ($content !== false) {
                        $hexContent = bin2hex($content);
                        return extractKid($hexContent);
                    }
                }
            }
        }
    }

    return null;
}

function extractKid($hexContent) {
    $psshMarker = "70737368";
    $psshOffset = strpos($hexContent, $psshMarker);
    
    if ($psshOffset !== false) {
        $headerSizeHex = substr($hexContent, $psshOffset - 8, 8);
        $headerSize = hexdec($headerSizeHex);
        $psshHex = substr($hexContent, $psshOffset - 8, $headerSize * 2);
        $kidHex = substr($psshHex, 68, 32);
        $newPsshHex = "000000327073736800000000edef8ba979d64acea3c827dcd51d21ed000000121210" . $kidHex;
        $pssh = base64_encode(hex2bin($newPsshHex));
        $kid = substr($kidHex, 0, 8) . "-" . substr($kidHex, 8, 4) . "-" . substr($kidHex, 12, 4) . "-" . substr($kidHex, 16, 4) . "-" . substr($kidHex, 20);
        
        return ['pssh' => $pssh, 'kid' => $kid];
    }
    
    return null;
}

if (strpos($dashUrl, 'https://bpaicatchup') !== 0) {
    header("Location: $dashUrl");
    exit;
}

$dashUrl = str_replace('bpaicatchupta', 'bpwcatchupta', $dashUrl);
if ($beginTimestamp) {
    $dashUrl = str_replace('master', 'manifest', $dashUrl);
    $dashUrl .= "?begin=$begin&end=$end";
}

$manifestContent = fetchMPDManifest($dashUrl, $userAgent, $hmac, $proxy, $proxyAuth) ?? exit;

if (strpos($manifestContent, '<TITLE>Access Denied</TITLE>') !== false && strpos($manifestContent, '<H1>Access Denied</H1>') !== false) {
    $manifestContent = fetchMPDManifest($dashUrl, $userAgent, $hmac, $proxy, $proxyAuth) ?? exit;
}

$baseUrl = dirname($dashUrl);
$widevinePssh = extractPsshFromManifest($manifestContent, $baseUrl, $userAgent, $beginTimestamp, $hmac, $proxy, $proxyAuth);
$processedManifest = str_replace('dash/', "$baseUrl/dash/", $manifestContent);

if ($widevinePssh) {
    $staticReplacements = [
        '<!-- Created with Broadpeak BkS350 Origin Packager  (version=1.12.8-28913) -->' => '<!-- Created by TsDevil  (version=2.0) -->',
        '<ContentProtection value="cenc" schemeIdUri="urn:mpeg:dash:mp4protection:2011"/>' => '<!-- Common Encryption -->
          <ContentProtection schemeIdUri="urn:mpeg:dash:mp4protection:2011" value="cenc" cenc:default_KID="' . $widevinePssh['kid'] . '">
          </ContentProtection>',
        '<ContentProtection schemeIdUri="urn:uuid:edef8ba9-79d6-4ace-a3c8-27dcd51d21ed" value="Widevine"/>' => '<!-- Widevine -->
          <ContentProtection schemeIdUri="urn:uuid:EDEF8BA9-79D6-4ACE-A3C8-27DCD51D21ED">
            <cenc:pssh>' . $widevinePssh['pssh'] . '</cenc:pssh>
          </ContentProtection>',
    ];
    $processedManifest = str_replace(array_keys($staticReplacements), array_values($staticReplacements), $processedManifest);
    $processedManifest = strtr($processedManifest, ['.dash' => '.dash?' . $hmac, '.m4s' => '.m4s?' . $hmac]);
}

echo $processedManifest;
?>
