# 🏷️ 50OFF — Automatic 50%+ Deals Aggregator

A PHP-based deals aggregator website that automatically scrapes Amazon, Walmart, and Target for deals with **50% off or more**, and earns through affiliate links.

---

## 📁 Project Structure

```
50off/
├── index.php              ← Homepage (deal listing)
├── deal.php               ← Individual deal page
├── search.php             ← Search results
├── go.php                 ← Affiliate redirect + click tracking
├── setup.sql              ← Database setup (run once)
├── .htaccess              ← Apache URL routing + security
│
├── includes/
│   ├── db.php             ← PDO database connection
│   ├── functions.php      ← Core helpers
│   ├── header.php         ← Shared HTML header
│   ├── footer.php         ← Shared HTML footer
│   └── deal_card.php      ← Reusable deal card component
│
├── scraper/
│   ├── BaseScraper.php    ← Abstract base class
│   ├── AmazonScraper.php  ← Amazon scraper
│   ├── WalmartScraper.php ← Walmart scraper
│   ├── TargetScraper.php  ← Target scraper (RedSky API)
│   └── run.php            ← Cron runner script
│
├── admin/
│   └── index.php          ← Admin dashboard
│
├── api/
│   ├── suggestions.php    ← Live search autocomplete
│   └── track.php          ← Click tracking endpoint
│
└── assets/
    ├── css/style.css      ← Main stylesheet
    ├── js/main.js         ← Frontend JS
    └── images/
        └── placeholder.svg
```

---

## 🚀 Local Setup (XAMPP / WAMP / Laragon)

### 1. Place files
Copy the `50off/` folder into your web server root:
- XAMPP: `C:/xampp/htdocs/50off/`
- WAMP: `C:/wamp/www/50off/`
- Laragon: `C:/laragon/www/50off/`

### 2. Set up database
1. Open phpMyAdmin → `http://localhost/phpmyadmin`
2. Create a new database named `50off_db`
3. Import `setup.sql` (this creates all tables + demo data)

### 3. Configure database credentials
Edit `includes/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', '50off_db');
define('DB_USER', 'root');      // your MySQL username
define('DB_PASS', '');          // your MySQL password
```

### 4. Configure affiliate IDs
Edit `scraper/run.php`:
```php
$config = [
    'amazon_tag'        => 'YOUR-TAG-20',        // Amazon Associates
    'walmart_affiliate' => 'YOUR-AFFILIATE-ID',  // Walmart affiliate
    'target_affiliate'  => 'YOUR-AFFILIATE-ID',  // Target affiliate
];
```

### 5. Visit the site
Open `http://localhost/50off/` — you'll see the demo deals from `setup.sql`.

### 6. Run the scraper
```bash
php scraper/run.php
```
Or visit: `http://localhost/50off/admin/` (password: `admin123`)

---

## 🔧 Admin Panel

URL: `/admin/`  
Default password: **`admin123`** ← **Change this before going live!**

Change it in `admin/index.php`:
```php
$adminPass = 'your-secure-password-here';
```

Features:
- View active deal counts by store
- Feature/unfeature specific deals
- Hide/remove deals
- Run scraper manually
- View scraper logs

---

## ⏰ Setting Up Auto-Scraping (Cron)

### Hostinger (via cPanel):
1. Login → Advanced → Cron Jobs
2. Add cron: `0 */2 * * *`
3. Command: `php /home/username/public_html/scraper/run.php`

### Linux VPS:
```bash
crontab -e
# Add this line (runs every 2 hours):
0 */2 * * * /usr/bin/php /var/www/html/50off/scraper/run.php >> /var/log/50off.log 2>&1
```

### Vercel (use Vercel Cron or external cron service like cron-job.org):
Note: Vercel doesn't support PHP natively. For Vercel hosting, you'd need to rewrite in Node.js or use a PHP-capable host like Hostinger.

---

## 💰 Affiliate Program Links

| Store | Signup URL |
|-------|-----------|
| Amazon Associates | https://affiliate-program.amazon.com |
| Walmart Creator | https://affiliates.walmart.com |
| Target Affiliates | https://partners.target.com |
| Best Buy Affiliates | https://www.bestbuy.com/site/misc/affiliate-program |

---

## 🌐 Deploying to Hostinger

1. Upload all files via File Manager or FTP
2. Set document root to your `public_html` folder
3. Import `setup.sql` via Hostinger's phpMyAdmin
4. Update `includes/db.php` with Hostinger's DB credentials
5. Set up cron job in cPanel
6. **Change admin password** in `admin/index.php`

---

## ⚠️ Important Notes

- **Scraping**: Be respectful of robots.txt and rate limits. Add delays between requests.
- **Affiliate compliance**: Add required affiliate disclosures (already in footer).
- **Prices**: Always note prices can change — deals are verified at time of scraping.
- **Legal**: Review each store's Terms of Service regarding scraping and affiliate rules.

---

## 🎨 Customization

- **Colors**: Edit CSS variables at the top of `assets/css/style.css`
- **Minimum discount**: Change `discount_pct >= 50` in `includes/functions.php`
- **Stores**: Add new scrapers in `scraper/` extending `BaseScraper`
- **Logo**: Replace the emoji in `header.php` with your own image

---

Built with ❤️ PHP 8.1+, MySQL 8, vanilla CSS & JS. No frameworks required.
