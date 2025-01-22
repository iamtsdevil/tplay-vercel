<?php
//error_reporting(0);
//ini_set('display_errors', 0);
$userAgent = 'DevilBhai/5.0 AppleWebKit/534.46.0';
$beginTimestamp = isset($_GET['utc']) ? intval($_GET['utc']) : null;
$endTimestamp = isset($_GET['lutc']) ? intval($_GET['lutc']) : null;
$begin = $beginTimestamp ? date('Ymd\THis', $beginTimestamp) : 'unknown';
$end = $endTimestamp ? date('Ymd\THis', $endTimestamp) : 'unknown';
$id = $_GET['id'] ?? exit;
//$hmacjson = file_get_contents('__cookie_.json');
$//hmacarray = json_decode($hmacjson, true);
//$hmac = $hmacarray[$id]['hmac'] ?? updateHmac($id);
//$channelInfo = getChannelInfo($id);
//$dashUrl = $channelInfo['channel_url'] ?? exit;
$dashUrl = 'https://bpaicatchupta7.akamaized.net/bpk-tv/irdeto_com_Channel_257/output/master.mpd';
$hmac = 'hdntl=exp=1737555655~acl=%2fbpk-tv%2firdeto_com_Channel_257%2foutput%2f*~id=1076415189~data=hdntl~hmac=73c35ce53874f9979bb016c3d74d39681f5220597ad10048af87f1dc3c9feef0';
function updateHmac($id){
        $trueUrl = 'https://tsdevil.fun/Devil2_0/tplay-api/channel-wise-hmac.php?id=' . $id;
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $trueUrl);
                    //curl_setopt($ch, CURLOPT_PROXY, "103.172.84.252:49155");
                    //curl_setopt($ch, CURLOPT_PROXYUSERPWD, "premiumreaper4060E5DA:DYsYsjJeTm");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_exec($ch);
                    curl_close($ch);
}
//$valid_ids = array_flip([8, 559, 24, 244, 245, 114, 551, 78, 235, 246, 463, 61, 469, 468, 516, 387, 388, 496, 467, 540, 292, 537, 1363, 137, 587, 733, 605, 413, 184, 484, 1181]);
//if (isset($valid_ids[$id])){

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
//echo $manifestContent;
$response = "<HTML><HEAD><TITLE>Access Denied</TITLE></HEAD><BODY><H1>Access Denied</H1>"; // Example response content

// Check if "Access Denied" HTML is in the response
if (strpos($manifestContent, '<TITLE>Access Denied</TITLE>') !== false && strpos($manifestContent, '<H1>Access Denied</H1>') !== false) {
    updateHmac($id);
    $manifestContent = fetchMPDManifest($dashUrl, $userAgent, $hmac) ?? exit;
}
$baseUrl = dirname($dashUrl);
$widevinePssh = extractPsshFromManifest($manifestContent, $baseUrl, $userAgent, $beginTimestamp, $hmac);
    $processedManifest = str_replace('dash/', "$baseUrl/dash/", $manifestContent);
    if ($widevinePssh) {
    
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
    
    // Apply all static replacements in a single str_replace call.
    $processedManifest = str_replace(array_keys($staticReplacements), array_values($staticReplacements), $processedManifest);
    
    // Apply dynamic replacements for .dash and .m4s extensions using strtr for efficiency.
    $processedManifest = strtr($processedManifest, [
        '.dash' => '.dash?' . $hmac,
        '.m4s' => '.m4s?' . $hmac
    ]);
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

//}else{
//    header("Location: https://tsdevil.fun/intro/dash/intro.php", true, 302);
//     exit();
//}  
function fetchMPDManifest(string $url, string $userAgent , string $hmac): ?string {
    
    $trueUrl = $url .'?'. $hmac;
    //echo $trueUrl;
    $h1 = [
        "User-Agent: $userAgent",
        //'Accept-Encoding: gzip',
        'Origin: https://watch.tataplay.com',
        'Referer: https://watch.tataplay.com/'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $trueUrl);
    // curl_setopt($ch, CURLOPT_PROXY, "103.172.84.252:49155");
    // curl_setopt($ch, CURLOPT_PROXYUSERPWD, "premi:pQA2G23yU6");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $h1);
    
    $content = curl_exec($ch);
    curl_close($ch);
    //echo $content;
    return $content !== false ? $content : null;
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
function extractPsshFromManifest(string $content, string $baseUrl, string $userAgent, ?int $beginTimestamp, string $hmac): ?array {
    if (($xml = @simplexml_load_string($content)) === false) return null;
    foreach ($xml->Period->AdaptationSet as $set) {
        if ((string)$set['contentType'] === 'audio') {
            foreach ($set->Representation as $rep) {
                $template = $rep->SegmentTemplate ?? null;
                if ($template) {
                    $startNumber = $beginTimestamp ? (int)($template['startNumber'] ?? 0) : (int)($template['startNumber'] ?? 0) + (int)($template->SegmentTimeline->S['r'] ?? 0);
                    $media = str_replace(['$RepresentationID$', '$Number$'], [(string)$rep['id'], $startNumber], $template['media']);
                    $trueUrl = "$baseUrl/dash/$media?" . $hmac;
                    //echo $trueUrl;
                    $h1 = [
                        "User-Agent: $userAgent",
                        //'Accept-Encoding: gzip',
                        'Origin: https://watch.tataplay.com',
                        'Referer: https://watch.tataplay.com/',
                    ];
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $trueUrl);
                    // curl_setopt($ch, CURLOPT_PROXY, "103.172.84.252:49155");
                    // curl_setopt($ch, CURLOPT_PROXYUSERPWD, "premi:pQA2G23yU6");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                    curl_setopt($ch, CURLOPT_HEADER, 1);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $h1);
                    
                    $content = curl_exec($ch);
                    //echo $content;
                    curl_close($ch);
                    
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
function getChannelInfo(string $id): array {
    $json = @file_get_contents('allchannels.json');
    $channels = $json !== false ? json_decode($json, true) : null;
    if ($channels === null) {
        exit;
    }
    foreach ($channels as $channel) {
        if ($channel['channel_id'] == $id) return $channel;
    }
    exit;
}
?>
