<?php
/**
 * WalmartScraper.php — walmart.com deals
 * ════════════════════════════════════════
 *
 * ⚠ STATUS: BLOCKED — Walmart uses Akamai Bot Manager which detects and blocks
 * all non-browser HTTP clients, including curl from datacenter IPs (Hostinger),
 * cloud runner IPs (GitHub Actions), and even local residential IPs.
 *
 * All requests return either:
 *  - HTTP 307 → "blocked - redirecting" page (datacenter)
 *  - CAPTCHA page (residential / GitHub Actions)
 *
 * FUTURE OPTIONS:
 * ───────────────
 * 1. Walmart Affiliate API — official product feed, no scraping needed.
 *    Apply at: https://affiliates.walmart.com/
 *    Once approved, use the Product Search API with min_discount=50.
 *
 * 2. Walmart Open API — requires application:
 *    https://developer.walmart.com/
 *
 * 3. Residential proxy service (e.g. Bright Data, Oxylabs) — bypasses Akamai
 *    but adds cost and complexity.
 *
 * This stub logs the situation clearly and exits without wasting time retrying.
 */
require_once __DIR__ . '/BaseScraper.php';

class WalmartScraper extends BaseScraper
{
    protected string $store = 'walmart';

    public function scrape(): void
    {
        $this->say('=== Walmart Scraper — walmart.com ===');
        $this->say('  ⚠ Walmart is protected by Akamai Bot Manager.');
        $this->say('  ⚠ All curl requests are blocked (datacenter, cloud, and residential IPs).');
        $this->say('  ⚠ No deals scraped. Awaiting official Walmart Affiliate API access.');
        $this->say('     → Apply at: https://affiliates.walmart.com/');

        $this->logResult('error', 'Walmart blocked by Akamai Bot Manager — 0 deals. Needs official affiliate API.');
    }
}
