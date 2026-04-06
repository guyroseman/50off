<?php
/**
 * blog/update-posts.php — Enhance existing blog posts with SEO content
 * Adds: FAQ sections, more internal links, geo content, cross-post links
 * Run ONCE then DELETE.
 */
define('ROOT', dirname(__DIR__));
require_once ROOT . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

$db = getDB();

// ── Shared FAQ appends per post slug ─────────────────────────────────────────
$updates = [];

// ── POST 1: best-amazon-deals-this-week ─────────────────────────────────────
$updates['best-amazon-deals-this-week'] = [
'content_append' => <<<HTML

<h2>Frequently Asked Questions</h2>

<h3>How do I find the best Amazon deals this week?</h3>
<p>Browse <a href="/">50offsale.com</a> — our scrapers check Amazon every 3 hours and only show items at 50% off or more. Filter by category using the bar at the top to narrow down electronics, kitchen, clothing, or home deals.</p>

<h3>Are Amazon deals really 50% off?</h3>
<p>Every deal on 50OFF is calculated from the original list price — we don't take Amazon's word for it, we verify the math. If a deal is 49.9% off, it doesn't show up.</p>

<h3>When does Amazon update deals?</h3>
<p>Amazon adds new deals continuously throughout the day. Lightning Deals run for 4–12 hours. Our database is updated every 3 hours so you always see current prices.</p>

<h3>Can I save Amazon deals to check later?</h3>
<p>Yes — <a href="/signup.php">create a free account</a> to save deals to your wishlist. Your saved deals are stored in your account and accessible from any device.</p>

<h3>What categories have the most Amazon deals?</h3>
<p>Electronics, kitchen appliances, clothing, and home goods consistently have the highest number of 50%+ off deals on Amazon. Sports and beauty categories also see frequent deep discounts.</p>

<h2>Popular Deals Near You</h2>
<p>50OFF tracks Amazon deals available for delivery across the United States — including major metros like <strong>New York, Los Angeles, Chicago, Houston, Phoenix, Philadelphia, San Antonio, San Diego</strong>, and everywhere Amazon delivers. All prices shown are standard Amazon pricing available nationwide.</p>

<p>Browse more deals: <a href="/?category=electronics">📱 Electronics</a> · <a href="/?category=kitchen">🍳 Kitchen</a> · <a href="/?category=home">🏠 Home</a> · <a href="/?category=clothing">👗 Clothing</a> · <a href="/blog/best-kitchen-deals-amazon">Best Kitchen Deals →</a></p>
HTML,
'meta_desc' => "This week's best Amazon deals at 50% off or more. Verified daily — electronics, kitchen, clothing, home goods. Only half-price deals, updated every 3 hours.",
];

// ── POST 2: best-deals-under-50-dollars ─────────────────────────────────────
$updates['best-deals-under-50-dollars'] = [
'content_append' => <<<HTML

<h2>Frequently Asked Questions</h2>

<h3>Where can I find real deals under $50?</h3>
<p>50offsale.com aggregates deals from Amazon, Target, eBay, and 6pm — filtering for only 50% off or better. On the <a href="/">homepage</a>, every product listed is half price or less.</p>

<h3>What types of products are under $50 at 50% off?</h3>
<p>Clothing, kitchen gadgets, beauty products, sports gear, and small electronics are the most common categories. At 50%+ off, you'll find items originally priced $100+ now available for under $50.</p>

<h3>Are these deals updated daily?</h3>
<p>Yes — we update deals every 3 hours automatically. Prices are current at time of browsing, though some flash deals sell out quickly.</p>

<h3>How do I filter deals by price on 50OFF?</h3>
<p>Use the category filters and search bar on the <a href="/">homepage</a> to find deals by category. The deals are sorted by discount percentage by default.</p>

<h2>Shop Under $50 — US Nationwide</h2>
<p>All deals shown ship to addresses across the United States — from <strong>New York and California</strong> to <strong>Texas, Florida, Illinois</strong> and beyond. Amazon Prime members get free 2-day shipping on most items.</p>

<p>Related guides: <a href="/blog/never-pay-full-price-amazon">How to Never Pay Full Price →</a> · <a href="/blog/best-kitchen-deals-amazon">Best Kitchen Deals →</a> · <a href="/?category=clothing">Browse Clothing Deals →</a></p>
HTML,
'meta_desc' => "Best deals under \$50 right now — all 50% off or more from Amazon and Target. Real half-price savings on clothing, kitchen gadgets, beauty, and more. Updated daily.",
];

// ── POST 3: how-50off-works ──────────────────────────────────────────────────
$updates['how-50off-works'] = [
'content_append' => <<<HTML

<h2>Frequently Asked Questions About 50OFF</h2>

<h3>What is 50OFF?</h3>
<p>50OFF (50offsale.com) is a deal aggregator that automatically finds products at 50% off or more from major US retailers including Amazon, Target, eBay, and 6pm. We update our database every 3 hours.</p>

<h3>Is 50OFF free to use?</h3>
<p>Yes, completely free. Browse all deals without an account. <a href="/signup.php">Create a free account</a> to save deals to your wishlist.</p>

<h3>Which stores does 50OFF track?</h3>
<p>We currently track <a href="/?store=amazon">Amazon</a>, <a href="/?store=target">Target</a>, <a href="/?store=ebay">eBay</a>, <a href="/?store=6pm">6pm</a>, and <a href="/?store=bestbuy">Best Buy</a>. We're adding more stores regularly.</p>

<h3>How do you make money?</h3>
<p>50OFF uses affiliate links. When you click a deal and purchase, we earn a small commission from the retailer — at no extra cost to you. We never show a deal just to earn commission; every deal is verified at 50% off or more.</p>

<h3>How do I search for specific products?</h3>
<p>Use the search bar at the top of any page. You can also filter by category (<a href="/?category=electronics">electronics</a>, <a href="/?category=kitchen">kitchen</a>, <a href="/?category=clothing">clothing</a>, etc.) or by store.</p>

<h3>Can I get alerts for new deals?</h3>
<p><a href="/signup.php">Create a free account</a> to save deals. We're building email alerts — sign up now to be first in line when it launches.</p>
HTML,
'meta_desc' => "How 50OFF works: we automatically scrape Amazon and Target every 3 hours to show only 50%+ off deals. Free to use, verified discounts, no coupons needed.",
];

// ── POST 4: best-kitchen-deals-amazon ────────────────────────────────────────
$updates['best-kitchen-deals-amazon'] = [
'content_append' => <<<HTML

<h2>Frequently Asked Questions</h2>

<h3>What kitchen items go on sale most often on Amazon?</h3>
<p>Air fryers, blenders, cookware sets, and kitchen storage containers are the most frequently discounted kitchen items on Amazon. These categories have many competing brands, which drives prices down aggressively.</p>

<h3>When is the best time to buy kitchen appliances on Amazon?</h3>
<p>Major sales events (Prime Day in July, Black Friday in November, Big Spring Sale in March) offer the deepest discounts. However, 50%+ off kitchen deals appear daily on 50OFF regardless of the season.</p>

<h3>Are Amazon kitchen deals safe to buy?</h3>
<p>Look for products with 4+ stars and 200+ reviews. Buy from Amazon directly or from sellers with high ratings. All items shown on 50OFF link directly to Amazon product pages so you can check reviews before purchasing.</p>

<h3>Do kitchen deals qualify for Amazon Prime shipping?</h3>
<p>Most kitchen items on Amazon qualify for Prime free 2-day shipping. Look for the Prime badge on the product page to confirm.</p>

<h2>Kitchen Deals Available Nationwide</h2>
<p>All deals ship across the US — including <strong>California, Texas, New York, Florida, Illinois, Pennsylvania, Ohio, Georgia</strong>, and all 50 states. Prime members get free fast shipping on most orders.</p>

<p>Browse more: <a href="/?category=kitchen">All kitchen deals at 50% off →</a> · <a href="/blog/best-amazon-deals-this-week">Best Amazon Deals This Week →</a> · <a href="/blog/never-pay-full-price-amazon">How to Never Pay Full Price →</a></p>
HTML,
'meta_desc' => "Best kitchen deals on Amazon at 50% off or more — air fryers, blenders, cookware sets, gadgets. Updated daily. Find half-price kitchen products right now.",
];

// ── POST 5: best-headphones-audio-deals ────────────────────────────────────
$updates['best-headphones-audio-deals'] = [
'content_append' => <<<HTML

<h2>Frequently Asked Questions</h2>

<h3>Which headphone brands have the best Amazon deals?</h3>
<p>Sony, Skullcandy, Soundcore (Anker), JLab, TOZO, and Jabra regularly run 50%+ off promotions on Amazon. Mid-range brands discount most aggressively to compete with premium brands like Bose and Apple.</p>

<h3>Are 50% off headphones worth buying?</h3>
<p>Absolutely. A $100 headphone at 50% off is $50 — often better quality than many $50-list-price options. Check review counts: 4.2+ stars with 500+ reviews is a reliable quality signal.</p>

<h3>What's the difference between noise-canceling and noise-isolating headphones?</h3>
<p>Active noise cancellation (ANC) uses microphones and electronics to cancel ambient sound — great for travel and offices. Noise isolation uses physical sealing (padding) to block sound passively — simpler, cheaper, still effective for casual use.</p>

<h3>Do wireless headphones work with all phones?</h3>
<p>Yes — Bluetooth headphones work with Android, iPhone, and most devices. ANC features may not fully integrate with all operating systems, but basic audio playback and calls work universally.</p>

<h2>Headphone Deals Available Across the US</h2>
<p>All headphone deals on 50OFF ship to every state — <strong>New York, California, Texas, Florida</strong> and more. Prime members get free 2-day delivery on eligible items.</p>

<p>Browse more: <a href="/?category=electronics">All electronics deals →</a> · <a href="/blog/best-amazon-deals-this-week">Best Amazon Deals This Week →</a> · <a href="/blog/best-deals-under-50-dollars">Best Deals Under $50 →</a></p>
HTML,
'meta_desc' => "Best headphone and audio deals at 50% off — wireless earbuds, noise-canceling headphones, Bluetooth speakers. Updated daily from Amazon. Find half-price audio gear now.",
];

// ── POST 6: target-clearance-guide ──────────────────────────────────────────
$updates['target-clearance-guide'] = [
'content_append' => <<<HTML

<h2>Frequently Asked Questions About Target Clearance</h2>

<h3>How do I find Target clearance items online?</h3>
<p>Browse <a href="/?store=target">50OFF's Target deals page</a> — we automatically track Target clearance prices and only show items at 50% off or more. Much faster than digging through Target's own website.</p>

<h3>Does Target do additional markdowns?</h3>
<p>Yes. Target regularly marks clearance items down again if they don't sell — typically from 30% to 50% to 70% to 90% off over a few weeks. Items at 70-90% off are often near end-of-cycle.</p>

<h3>Can I return Target clearance items?</h3>
<p>Yes — Target's standard return policy applies to clearance items. Most items can be returned within 90 days with a receipt. Some items (electronics, opened software) have shorter windows.</p>

<h3>What are the best Target clearance categories?</h3>
<p>Toys (especially after the holidays), home decor, furniture, clothing, and seasonal items see the deepest Target clearance discounts. Target Plus marketplace items also frequently hit 50-80% off.</p>

<h3>Is Target Plus different from regular Target?</h3>
<p>Target Plus is Target's marketplace where third-party sellers list products alongside Target's own inventory. These sellers can offer very aggressive prices — often 50–80% off — on furniture, home goods, and more.</p>

<h2>Target Deals Available Nationwide</h2>
<p>Target has over 1,800 stores across the US and ships online orders to all 50 states. 50OFF tracks Target online deals available in <strong>California, Texas, New York, Florida, Illinois, Ohio</strong> and everywhere else in the country.</p>

<p>Browse: <a href="/?store=target">All Target deals at 50%+ off →</a> · <a href="/?category=home">Home category deals →</a> · <a href="/?category=toys">Toy deals →</a> · <a href="/blog/best-amazon-deals-this-week">Amazon deals this week →</a></p>
HTML,
'meta_desc' => "Complete guide to finding Target clearance deals at 50% off or more. How Target markdowns work, Target Plus marketplace tips, and where to find the best deals online.",
];

// ── POST 7: never-pay-full-price-amazon ─────────────────────────────────────
$updates['never-pay-full-price-amazon'] = [
'content_append' => <<<HTML

<h2>Frequently Asked Questions</h2>

<h3>Does Amazon ever really have 50% off deals?</h3>
<p>Yes, constantly. Amazon has millions of products and thousands of sellers competing, which creates regular 50%+ off promotions — especially in categories like clothing, kitchen gadgets, electronics accessories, and home goods. 50OFF automatically surfaces these deals.</p>

<h3>How do I know if an Amazon deal is a real discount?</h3>
<p>Compare the sale price to Amazon's own historical price data. Sellers occasionally inflate the "original" price, but at 50OFF we verify the discount calculation. Reviews also help — a product with thousands of reviews at a consistent price history is more trustworthy than a no-name brand with inflated pricing.</p>

<h3>What's the best app for tracking Amazon deals?</h3>
<p>Bookmark <a href="/">50offsale.com</a> on your phone for easy access to 50%+ off deals updated every 3 hours. We're mobile-optimized so it works like an app.</p>

<h3>Are Amazon coupons worth clipping?</h3>
<p>Yes — "Clip coupon" checkboxes on Amazon product pages often add 5–20% off on top of existing sale prices. Always check before adding to cart. If a product is already 40% off and has a 10% coupon, you've hit the 50% threshold.</p>

<h3>When is the best time to buy electronics on Amazon?</h3>
<p>Prime Day (July), Black Friday (November), and Cyber Monday offer the biggest annual electronics discounts. But deals at 50%+ off appear year-round — especially when new models launch and older versions get discounted.</p>

<h2>Smart Shopping Across the US</h2>
<p>Whether you're shopping in <strong>New York, LA, Chicago, Houston</strong> or anywhere else in America, Amazon prices are the same nationwide. 50OFF helps you find the right moment to buy — so you never overpay regardless of where you live.</p>

<p>Put it into practice: <a href="/">Browse all deals at 50%+ off →</a> · <a href="/blog/best-amazon-deals-this-week">This week's best deals →</a> · <a href="/blog/how-50off-works">How 50OFF works →</a></p>
HTML,
'meta_desc' => "5 proven strategies to never pay full price on Amazon again. Deal timing, lightning deals, clip coupons, and the easiest way to find 50%+ off deals every day.",
];

// ── POST 8: best-bedding-deals-amazon ────────────────────────────────────────
$updates['best-bedding-deals-amazon'] = [
'content_append' => <<<HTML

<h2>Frequently Asked Questions</h2>

<h3>What thread count should I look for in bedding deals?</h3>
<p>For everyday use, 300–500 thread count in quality cotton or microfiber is the sweet spot. Higher thread counts don't always mean better quality — focus on material (long-staple cotton, microfiber) and verified customer reviews.</p>

<h3>Are Amazon bedding deals actually good quality?</h3>
<p>Many are. Look for sets with 4+ stars and 1,000+ reviews. Brands like Mellanni, CGK Unlimited, and Sweet Home Collection consistently receive strong reviews at discounted prices. Avoid unknown brands with few reviews even if the discount is large.</p>

<h3>What size bed does the bedding fit?</h3>
<p>Sheet sets come in Twin, Twin XL, Full, Queen, King, and California King. Always verify the size in the product title before purchasing — Amazon shows size options on the product page.</p>

<h3>Do bedding deals qualify for Prime shipping?</h3>
<p>Most Amazon bedding items qualify for free Prime 2-day shipping. Heavier comforters may have slightly different shipping timelines, but standard sheet sets ship fast.</p>

<h3>When are the best bedding sales?</h3>
<p>January (post-holiday clearance), Prime Day (July), Labor Day, and Black Friday typically bring the deepest bedding discounts. But 50%+ off bedding deals appear on Amazon year-round — we track them automatically.</p>

<h2>Bedding Deals Delivered Across the US</h2>
<p>All Amazon bedding deals ship nationwide — to <strong>New York, California, Texas, Florida, Pennsylvania</strong> and all 50 states. Prime members get free fast delivery on most orders.</p>

<p>Browse more home deals: <a href="/?category=home">All home deals at 50%+ off →</a> · <a href="/blog/best-amazon-deals-this-week">Amazon deals this week →</a> · <a href="/blog/best-deals-under-50-dollars">Best deals under $50 →</a></p>
HTML,
'meta_desc' => "Best Amazon bedding deals — sheet sets, comforters, and pillows at 50% off or more. Updated daily. Find high-rated bedding at half price right now.",
];

// ── Apply updates ────────────────────────────────────────────────────────────
echo "Updating blog posts...\n\n";
$updated = 0;

foreach ($updates as $slug => $changes) {
    // Get current content
    $stmt = $db->prepare("SELECT id, content FROM blog_posts WHERE slug = ?");
    $stmt->execute([$slug]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        echo "NOT FOUND: {$slug}\n";
        continue;
    }

    // Check if FAQ already appended
    if (str_contains($post['content'], 'Frequently Asked Questions')) {
        echo "SKIP (already has FAQ): {$slug}\n";
        continue;
    }

    $newContent = $post['content'] . ($changes['content_append'] ?? '');
    $params     = [':content' => $newContent, ':id' => $post['id']];

    $sql = "UPDATE blog_posts SET content = :content";
    if (!empty($changes['meta_desc'])) {
        $sql .= ", meta_desc = :meta_desc";
        $params[':meta_desc'] = $changes['meta_desc'];
    }
    $sql .= " WHERE id = :id";

    $db->prepare($sql)->execute($params);
    echo "UPDATED: {$slug}\n";
    $updated++;
}

echo "\nDone! Updated {$updated} posts.\n";
echo "DELETE THIS FILE after running.\n";
echo "rm " . __FILE__ . "\n";
