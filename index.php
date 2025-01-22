<?php
//error_reporting(0);
//ini_set('display_errors', 0);
$userAgent = 'DevilBhai/5.0 AppleWebKit/534.46.0';
$beginTimestamp = isset($_GET['utc']) ? intval($_GET['utc']) : null;
$endTimestamp = isset($_GET['lutc']) ? intval($_GET['lutc']) : null;
$begin = $beginTimestamp ? date('Ymd\THis', $beginTimestamp) : 'unknown';
$end = $endTimestamp ? date('Ymd\THis', $endTimestamp) : 'unknown';
$id = $_GET['id'] ?? exit;

$dashUrl = 'https://bpaicatchupta7.akamaized.net/bpk-tv/irdeto_com_Channel_257/output/master.mpd';
$hmac = 'hdntl=exp=1737555655~acl=%2fbpk-tv%2firdeto_com_Channel_257%2foutput%2f*~id=1076415189~data=hdntl~hmac=73c35ce53874f9979bb016c3d74d39681f5220597ad10048af87f1dc3c9feef0';

function updateHmac($id) {
    $trueUrl = 'https://tsdevil.fun/Devil2_0/tplay-api/channel-wise-hmac.php?id=' . $id;

    $contextOptions = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: DevilBhai/5.0 AppleWebKit/534.46.0\r\n"
        ]
    ];
    $context = stream_context_create($contextOptions);
    file_get_contents($trueUrl, false, $context);
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

$manifestContent = fetchMPDManifest($dashUrl, $userAgent, $hmac) ?? exit;
echo $manifestContent;

if (strpos($manifestContent, '<TITLE>Access Denied</TITLE>') !== false && strpos($manifestContent, '<H1>Access Denied</H1>') !== false) {
    updateHmac($id);
    $manifestContent = fetchMPDManifest($dashUrl, $userAgent, $hmac) ?? exit;
}

$baseUrl = dirname($dashUrl);
$widevinePssh = extractPsshFromManifest($manifestContent, $baseUrl, $userAgent, $beginTimestamp, $hmac);
$processedManifest = str_replace('dash/', "$baseUrl/dash/", $manifestContent);

if ($widevinePssh) {
    // Apply static and dynamic replacements
    $staticReplacements = [
        '<!-- Created with Broadpeak BkS350 Origin Packager  (version=1.12.8-28913) -->' => '<!-- Created with love by TsDevil  (version=1.0) -->',
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

if (in_array($id, ['244', '599'])) {
    $processedManifest = str_replace(
        'minBandwidth="226400" maxBandwidth="3187600" maxWidth="1920" maxHeight="1080"',
        'minBandwidth="226400" maxBandwidth="2452400" maxWidth="1280" maxHeight="720"',
        $processedManifest
    );
    $processedManifest = preg_replace('/<Representation id="video=3187600" bandwidth="3187600".*?<\/Representation>/s', '', $processedManifest);
}

header('Content-Security-Policy: default-src \'self\';');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/dash+xml');
header("Cache-Control: max-age=20, public");
header('Content-Disposition: attachment; filename="TsDevil_' . urlencode($id) . '.mpd"');
echo $processedManifest;

function fetchMPDManifest(string $url, string $userAgent, string $hmac): ?string {
    $trueUrl = $url . '?' . $hmac;

    $contextOptions = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: $userAgent\r\nOrigin: https://watch.tataplay.com\r\nReferer: https://watch.tataplay.com/\r\n"
        ]
    ];
    $context = stream_context_create($contextOptions);
    $content = @file_get_contents($trueUrl, false, $context);

    return $content !== false ? $content : null;
}
?>
