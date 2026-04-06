<?php
/**
 * blog/gen-images.php — One-time script to generate Recraft AI images for blog posts
 * Run ONCE on Hostinger via browser or SSH, then DELETE this file.
 *
 * Usage: https://50offsale.com/blog/gen-images.php
 */

define('ROOT', dirname(__DIR__));
require_once ROOT . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

define('RECRAFT_API_KEY', 'rpTviuUVmdVsCpEbHBrHW7zReNROotnR4arYowFDstasDgKqwTASN0MHSW7uA32H');
define('RECRAFT_ENDPOINT', 'https://external.api.recraft.ai/v1/images/generations');
define('IMG_DIR', ROOT . '/assets/blog-images');
define('IMG_URL_BASE', '/assets/blog-images');

// Create directory
if (!is_dir(IMG_DIR)) mkdir(IMG_DIR, 0755, true);

// Tailored prompts per blog post slug
$prompts = [
    'best-amazon-deals-this-week' => [
        'prompt' => 'E-commerce deals concept, Amazon-style cardboard boxes and shopping bags with bold 50% OFF discount tags in red and orange, flat lay arrangement on clean white background, professional product photography, bright studio lighting, photorealistic',
        'style'  => 'realistic_image',
    ],
    'best-deals-under-50-dollars' => [
        'prompt' => 'Budget savings concept, crisp fifty dollar bill surrounded by colorful sale price tags, shopping cart icon, coins, clean minimalist photography on white background, high contrast, professional commercial photography',
        'style'  => 'realistic_image',
    ],
    'how-50off-works' => [
        'prompt' => 'Person browsing online deals on laptop at home, screen displays large discount percentages and shopping cart, cozy home office atmosphere, warm natural light, lifestyle photography, modern and approachable',
        'style'  => 'realistic_image',
    ],
    'best-kitchen-deals-amazon' => [
        'prompt' => 'Premium kitchen appliances arranged in flat lay: sleek air fryer, modern blender, stainless cookware set, kitchen utensils, all with red sale tags showing 50% off, white marble background, professional studio product photography',
        'style'  => 'realistic_image',
    ],
    'best-headphones-audio-deals' => [
        'prompt' => 'Premium over-ear wireless headphones and true wireless earbuds on dark textured background, subtle dramatic lighting, 50% off sale badge, tech product photography, glossy finish, ultra detailed',
        'style'  => 'realistic_image',
    ],
    'target-clearance-guide' => [
        'prompt' => 'Shopping cart filled with merchandise and clearance sale stickers, vibrant red sale signs in retail store aisle background, bright commercial photography, deals and savings theme',
        'style'  => 'realistic_image',
    ],
    'never-pay-full-price-amazon' => [
        'prompt' => 'Hands holding smartphone displaying Amazon deals app with green checkmarks and discount percentages, coins and savings jar beside it, clean light background, modern lifestyle photography, financial savings concept',
        'style'  => 'realistic_image',
    ],
    'best-bedding-deals-amazon' => [
        'prompt' => 'Luxurious white bedding set with fluffy pillows and duvet on neatly made bed, soft warm morning light through sheer curtains, cozy bedroom interior lifestyle photography, serene and aspirational',
        'style'  => 'realistic_image',
    ],
];

$db = getDB();
$posts = $db->query("SELECT id, slug, og_image FROM blog_posts WHERE is_published = 1")->fetchAll(PDO::FETCH_ASSOC);

$generated = 0;
$skipped   = 0;
$errors    = 0;

foreach ($posts as $post) {
    $slug = $post['slug'];

    // Skip if image already exists on disk
    $imgPath = IMG_DIR . '/' . $slug . '.jpg';
    if (file_exists($imgPath)) {
        echo "SKIP (exists): {$slug}\n";
        $skipped++;
        // Ensure DB is updated even if file already exists
        if (empty($post['og_image'])) {
            $db->prepare("UPDATE blog_posts SET og_image = ? WHERE id = ?")
               ->execute([IMG_URL_BASE . '/' . $slug . '.jpg', $post['id']]);
        }
        continue;
    }

    if (!isset($prompts[$slug])) {
        echo "SKIP (no prompt): {$slug}\n";
        $skipped++;
        continue;
    }

    $config = $prompts[$slug];
    echo "Generating image for: {$slug} ... ";
    flush();

    // Call Recraft API
    $payload = json_encode([
        'prompt' => $config['prompt'],
        'model'  => 'recraftv3',
        'style'  => $config['style'],
        'size'   => '1365x1024',
        'n'      => 1,
    ]);

    $ch = curl_init(RECRAFT_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . RECRAFT_API_KEY,
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        echo "ERROR (API {$httpCode})\n";
        $errors++;
        continue;
    }

    $data = json_decode($response, true);
    $imageUrl = $data['data'][0]['url'] ?? null;

    if (!$imageUrl) {
        echo "ERROR (no URL in response: " . substr($response, 0, 200) . ")\n";
        $errors++;
        continue;
    }

    // Download image
    $imgData = file_get_contents($imageUrl);
    if (!$imgData) {
        echo "ERROR (download failed)\n";
        $errors++;
        continue;
    }

    file_put_contents($imgPath, $imgData);

    // Update DB
    $relUrl = IMG_URL_BASE . '/' . $slug . '.jpg';
    $db->prepare("UPDATE blog_posts SET og_image = ? WHERE id = ?")->execute([$relUrl, $post['id']]);

    echo "OK → {$relUrl}\n";
    flush();
    $generated++;
    sleep(1); // Be nice to the API
}

echo "\n--- Done ---\n";
echo "Generated: {$generated}\n";
echo "Skipped:   {$skipped}\n";
echo "Errors:    {$errors}\n";
echo "\nDELETE THIS FILE after running: it contains your API key.\n";
echo "rm " . __FILE__ . "\n";
