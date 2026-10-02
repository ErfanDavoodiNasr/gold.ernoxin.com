<?php

// Merged learn article bodies — not under config/ so price/API requests never parse them.
$articles = require __DIR__ . '/learn_articles.php';
$inline = require __DIR__ . '/learn_inline_pages.php';
$karatMaster = require __DIR__ . '/learn_karat_master.php';
$pricingMaster = require __DIR__ . '/learn_pricing_master.php';
$formulasMaster = require __DIR__ . '/learn_formulas_master.php';
$taxInvoiceMaster = require __DIR__ . '/learn_tax_invoice_master.php';
$usedGoldMaster = require __DIR__ . '/learn_used_gold_master.php';
$digitalGoldMaster = require __DIR__ . '/learn_digital_gold_master.php';
$goldFundsMaster = require __DIR__ . '/learn_gold_funds_master.php';
$investmentMaster = require __DIR__ . '/learn_investment_master.php';

$consolidatedAway = [
    '18k-gold',
    '24k-gold',
    'gold-karat-difference',
    '24k-vs-18k-gold-investment',
    'gold-coin-vacuum-package',
    'how-gold-price-is-set',
    'gold-price-factors',
    'gold-price-guide',
    'gold-mozaneh',
    'gold-price-calculation',
    'melted-gold-price-calculation',
    'gold-ounce-mesghal',
    'buy-sell-gold-price-spread',
    'gold-difference-buy-sell',
    'melted-guide-gold-price-formulas',
    // Phase 3: Tax & Invoicing
    'gold-vat',
    'gold-tax-invoice-1405-guide',
    'seller-profit-in-gold-invoice',
    // Phase 4: Second-hand & Scrap
    'used-vs-new-gold',
    'second-hand-gold-buying-checklist',
    'second-hand-gold-without-invoice',
    'is-no-wage-gold-always-better',
    'why-some-gold-loses-more-on-resale',
    'melted-gold-or-used-gold-investment',
    // Phase 5: Digital Gold
    'digital-gold-platform-guide',
    'digital-gold-license-checklist',
    'digital-gold-physical-delivery-guide',
    'digital-gold-fees-spread-guide',
    'digital-gold-custody-risk-guide',
    'digital-gold-empty-selling-risk',
    'digital-gold-vs-etf-vs-physical',
    'digital-gold-tax-invoice-records',
    'digital-gold-small-saving-plan',
    'digital-gold-app-trust-checklist',
    // Phase 6: Investment Strategies
    'gold-investment-short-term-guide',
    'gold-investment-long-term-guide',
    'gold-investment-portfolio-allocation',
    'coin-vs-gold-short-term',
    'gold-dca-investment-strategy',
    'gold-investment-risk-management',
    'gold-investment-exit-strategy',
    'gold-vs-dollar-investment-iran',
    'gold-etf-short-term-trading-risks',
    'gold-investment-mistakes-beginners',
];
foreach ($consolidatedAway as $slug) {
    unset($inline[$slug], $articles[$slug]);
}

return $taxInvoiceMaster
    + $usedGoldMaster
    + $digitalGoldMaster
    + $goldFundsMaster
    + $investmentMaster
    + $pricingMaster
    + $formulasMaster
    + $karatMaster
    + $articles
    + $inline;
