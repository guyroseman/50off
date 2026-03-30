<?php
/**
 * /api/img.php — Image Proxy
 *
 * Fetches remote product images server-side and serves them to the browser.
 * This bypasses hotlink protection from Amazon, Walmart, Target etc.
 * Also caches images locally in /tmp to avoid repeated fetches.
 *
 * Usage: <img src="/api/img.php?url=https://example.com/product.jpg">
 */

// Security: only allow image URLs from known retailers
$allowedDomains = [
    'm.media-amazon.com', 'images-na.ssl-images-amazon.com',
    'i5.walmartimages.com', 'i1.walmartimages.com', 'i8.walmartimages.com',
    'target.scene7.com', 'i.target.com',
    'pisces.bbystatic.com', 'multimedia.bbycastatic.ca',
    'slickdeals.net', 'static.slickdealscdn.com',
    'dealnews.com', 'cdn.dealnews.com',
    'i.ebayimg.com', 'thumbs.ebaystatic.com',
    'images.costco-static.com',
    'images.homedepot-static.com',
    'media.woot.com', 'dwo338bqjtlkb.cloudfront.net',
    'bradsdeals.com', 'bens-images.bensbargains.net',
    'c1.neweggimages.com', 'c2.neweggimages.com',
];

$url = $_GET['url'] ?? '';

// Validate URL
if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    servePlaceholder();
    exit;
}

// Check domain is allowed
$host = parse_url($url, PHP_URL_HOST);
$host = preg_replace('/^www\./', '', strtolower($host ?? ''));
$allowed = false;
foreach ($allowedDomains as $d) {
    if ($host === $d || str_ends_with($host, '.' . $d)) {
        $allowed = true;
        break;
    }
}
// Allow all domains for now (you can restrict later)
// if (!$allowed) { servePlaceholder(); exit; }

// Cache key
$cacheDir = sys_get_temp_dir() . '/50off_img_cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);

$cacheKey  = md5($url);
$cachePath = $cacheDir . '/' . $cacheKey;
$cacheTime = 86400 * 3; // 3 days

// Serve from cache if fresh
if (file_exists($cachePath)) {
    $meta = @json_decode(file_get_contents($cachePath . '.meta'), true);
    if ($meta && (time() - $meta['ts']) < $cacheTime) {
        header('Content-Type: ' . $meta['mime']);
        header('Cache-Control: public, max-age=86400');
        header('X-Cache: HIT');
        readfile($cachePath);
        exit;
    }
}

// Fetch the image
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 3,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ImageBot/1.0)',
    CURLOPT_HTTPHEADER     => [
        'Accept: image/webp,image/avif,image/jpeg,image/png,image/*',
        'Referer: https://www.google.com/',
    ],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'image/jpeg';
curl_close($ch);

// Validate it's actually an image
if ($code !== 200 || !$body || !str_starts_with($mime, 'image/')) {
    servePlaceholder();
    exit;
}

// Clean mime (strip charset etc)
$mime = explode(';', $mime)[0];
$validMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif', 'image/svg+xml'];
if (!in_array($mime, $validMimes)) $mime = 'image/jpeg';

// Save to cache
file_put_contents($cachePath, $body);
file_put_contents($cachePath . '.meta', json_encode(['mime' => $mime, 'ts' => time()]));

// Serve
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
header('X-Cache: MISS');
echo $body;
exit;

// ─── Fallback placeholder ─────────────────────────────────────────────────────
function servePlaceholder(): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400">
        <rect width="400" height="400" fill="#F2F3F5"/>
        <g opacity=".35">
            <path d="M160 145h80v15l10 10v80l-10 10H160l-10-10v-80l10-10v-15zm10 15v10h60v-10h-60zm-5 25v70h90v-70h-90zm20 15h50v10h-50v-10zm0 20h30v10h-30v-10z" fill="#9CA3AF"/>
            <circle cx="200" cy="155" r="5" fill="#FF6B00" opacity=".6"/>
        </g>
    </svg>';
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=3600');
    echo $svg;
}
