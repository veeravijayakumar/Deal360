<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Deal360.shop | Buy, Sell Houses, Cars, Electronics & Find Workers</title>
<meta name="description" content="India's trusted marketplace for property, vehicles, electronics & local workers. OTP-verified users.">
<link rel="stylesheet" href="style.css">
</head><body>

<header class="header"><div class="container nav-wrap">
<a href="index.php" class="logo">deal<span>360</span><em>.shop</em></a>
<nav class="nav"><a href="listings.php">Browse Ads</a><a href="find-workers.php">Find Workers</a><a href="#plans">Plans</a></nav>
<div class="nav-actions"><a href="login.php" class="btn btn-outline">Login</a><a href="register.php" class="btn btn-primary">Post Free Ad</a></div>
</div></header>

<section class="hero"><div class="container">
<h1>Buy, Sell & Hire — <span>Everything Near You</span></h1>
<p>Verified sellers. Real buyers. Houses, cars, electronics & trusted local workers — all in one place.</p>
<form class="search-bar" action="listings.php" method="get">
<select name="segment" class="search-select"><option value="">All Categories</option><option value="property">🏠 Property</option><option value="vehicles">🚗 Vehicles</option><option value="electronics">📱 Electronics</option></select>
<input type="text" name="q" class="search-input" placeholder="Search 2BHK flat, Honda City, iPhone, electrician...">
<button type="submit" class="btn btn-accent">Search</button></form>
<div class="hero-stats">
<div><strong>4</strong><span>Segments</span></div><div><strong>100%</strong><span>OTP Verified</span></div>
<div><strong>🔒</strong><span>Safe Chat</span></div><div><strong>⚡</strong><span>Fast & Free</span></div></div>
</div></section>

<?php include 'featured-strip.php'; ?>

<section class="section" id="categories"><div class="container">
<h2 class="section-title">Explore Our <span>4 Segments</span></h2>
<p class="section-sub">Everything you need — organized, verified, easy.</p>
<div class="segments-grid">
<a href="listings.php?segment=property" class="segment-card"><div class="segment-icon" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);">🏠</div><h3>Property</h3><p>Houses, land, rentals & lease homes — full details with dropdowns.</p><span class="segment-link">Browse →</span></a>
<a href="listings.php?segment=vehicles" class="segment-card"><div class="segment-icon" style="background:linear-gradient(135deg,#f97316,#ea580c);">🚗</div><h3>Vehicles</h3><p>Cars, bikes & spare parts — brand, year, KM, owners.</p><span class="segment-link">Browse →</span></a>
<a href="listings.php?segment=electronics" class="segment-card"><div class="segment-icon" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);">📱</div><h3>Electronics</h3><p>Mobiles, TVs, laptops & appliances with condition & warranty.</p><span class="segment-link">Browse →</span></a>
<a href="find-workers.php" class="segment-card"><div class="segment-icon" style="background:linear-gradient(135deg,#10b981,#059669);">🔧</div><h3>Workers</h3><p>Electricians, plumbers, carpenters near you — hourly/day rates.</p><span class="segment-link">Find →</span></a>
</div></div></section>

<section class="section section-alt" id="plans"><div class="container">
<h2 class="section-title">Simple <span>Pricing</span></h2><p class="section-sub">Start free. Upgrade when you need more.</p>
<h3 class="plan-group-title">👤 For Buyers</h3>
<div class="plans-grid">
<div class="plan-card"><h4>Free</h4><div class="plan-price">₹0<span>/mo</span></div><ul><li>5 views/day</li><li>Browse all</li></ul></div>
<div class="plan-card"><h4>Silver</h4><div class="plan-price">₹99<span>/mo</span></div><ul><li>20 views/day</li><li>Email support</li></ul></div>
<div class="plan-card popular"><span class="badge">Most Popular</span><h4>Gold</h4><div class="plan-price">₹299<span>/mo</span></div><ul><li>50 views</li><li>✅ Enquire + Chat</li></ul></div>
<div class="plan-card"><h4>Platinum</h4><div class="plan-price">₹599<span>/mo</span></div><ul><li>♾️ Unlimited</li><li>Priority support</li></ul></div></div>
<h3 class="plan-group-title" style="margin-top:60px;">🏪 For Sellers</h3>
<div class="plans-grid">
<div class="plan-card"><h4>Free</h4><div class="plan-price">₹0<span>/mo</span></div><ul><li>2 ads/mo</li></ul></div>
<div class="plan-card"><h4>Starter</h4><div class="plan-price">₹99<span>/mo</span></div><ul><li>5 ads + 1 featured</li></ul></div>
<div class="plan-card popular"><span class="badge">Most Popular</span><h4>Growth</h4><div class="plan-price">₹299<span>/mo</span></div><ul><li>20 ads + 3 featured</li><li>📊 Analytics</li></ul></div>
<div class="plan-card"><h4>Pro</h4><div class="plan-price">₹599<span>/mo</span></div><ul><li>50 ads + 10 featured</li></ul></div>
<div class="plan-card"><h4>Unlimited</h4><div class="plan-price">₹999<span>/mo</span></div><ul><li>♾️ Everything</li></ul></div></div>
<div class="center"><a href="register.php" class="btn btn-accent">Start Free Now →</a></div></div></section>

<footer class="footer"><div class="container footer-grid">
<div><a href="index.php" class="logo footer-logo">deal<span>360</span><em>.shop</em></a><p>India's trusted 360° marketplace.</p></div>
<div><h4>Segments</h4><a href="listings.php?segment=property">Property</a><a href="listings.php?segment=vehicles">Vehicles</a><a href="listings.php?segment=electronics">Electronics</a><a href="find-workers.php">Workers</a></div>
<div><h4>Legal</h4><a href="#">Terms</a><a href="#">Privacy</a><a href="#">Refund Policy</a></div></div>
<div class="footer-bottom">© <?= date('Y') ?> deal360.shop — All Rights Reserved</div></footer>
</body></html>