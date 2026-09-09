<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/config/functions.php";

// --- Live DB Stats ---
$totalCustomers = (int)($conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'customer'")->fetch_assoc()["c"] ?? 0);
$completedOrders = (int)($conn->query("SELECT COUNT(*) AS c FROM orders WHERE status = 'Completed'")->fetch_assoc()["c"] ?? 0);
$totalReviews   = (int)($conn->query("SELECT COUNT(*) AS c FROM reviews")->fetch_assoc()["c"] ?? 0);

$avgRatingRow = $conn->query("SELECT ROUND(AVG(rating), 1) AS avg_r FROM reviews");
$avgRating = $avgRatingRow ? (float)($avgRatingRow->fetch_assoc()["avg_r"] ?? 0) : 0;

// Latest 3 reviews
$reviewsQuery = $conn->query("
    SELECT r.rating, r.comment, r.created_at, u.username
    FROM reviews r
    JOIN users u ON u.user_id = r.user_id
    ORDER BY r.created_at DESC, r.review_id DESC
    LIMIT 3
");
$latestReviews = $reviewsQuery ? $reviewsQuery->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | About Us</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="innovation.css">
</head>
<body>

<div class="page">
    <?php include __DIR__ . "/layout/navigation.php"; ?>

    <section class="about-section" style="padding-top: 120px; min-height: 80vh;">
        <div class="about-intro">
            <div class="about-label">OUR STORY</div>
            <div class="about-title">CRAFTED WITH <span>PASSION</span></div>
            <div class="small-green-line"></div>
            <p>
                Founded on the belief that a cup of coffee is more than just a morning routine, 
                HM Café is a warm sanctuary for thinkers, creators, and coffee lovers alike.
            </p>
            <p>
                We carefully source 100% ethically grown Arabica and Robusta beans from local and international farms, roasting each batch to bring out complex aromas and rich, velvety notes.
            </p>
            <div style="margin-top: 25px;">
                <a href="shop.php" class="hero-btn green-btn">VIEW MENU &amp; SHOP</a>
                <a href="book.php" class="hero-btn outline-btn" style="margin-left: 10px;">BOOK A TABLE</a>
            </div>
        </div>

        <div class="about-cards">
            <div class="about-card">
                <div class="about-icon">☕</div>
                <h3>OUR MISSION</h3>
                <p>To serve exceptionally brewed coffee and delicious artisanal food while creating unforgettable hospitality experiences for our neighborhood.</p>
            </div>

            <div class="about-card">
                <div class="about-icon">🌿</div>
                <h3>OUR VISION</h3>
                <p>To become the premier local café recognized for environmental sustainability, artisan barista craftsmanship, and community connection.</p>
            </div>

            <div class="about-card">
                <div class="about-icon people">👥</div>
                <h3>OUR CORE VALUES</h3>
                <p class="values">
                    • <strong>Quality:</strong> Perfection in every cup<br>
                    • <strong>Passion:</strong> Dedication to the craft<br>
                    • <strong>Community:</strong> A welcoming space for all<br>
                    • <strong>Integrity:</strong> Ethical and honest sourcing
                </p>
            </div>
        </div>
    </section>

    <!-- LIVE DB STATS STRIP -->
    <section class="reveal" style="padding: 50px 7%; background: #0a0a0a; border-top: 1px solid #1a1a1a; border-bottom: 1px solid #1a1a1a;">
        <div style="max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 30px; text-align: center;">
            <div>
                <div style="font-size: 42px; font-weight: 900; color: var(--green);" data-count="<?= $totalCustomers ?>" data-suffix="+"><?= number_format($totalCustomers) ?>+</div>
                <div style="font-size: 12px; color: #888; letter-spacing: 2px; margin-top: 6px; text-transform: uppercase;">Happy Customers</div>
            </div>
            <div>
                <div style="font-size: 42px; font-weight: 900; color: var(--green);" data-count="<?= $completedOrders ?>" data-suffix="+"><?= number_format($completedOrders) ?>+</div>
                <div style="font-size: 12px; color: #888; letter-spacing: 2px; margin-top: 6px; text-transform: uppercase;">Orders Completed</div>
            </div>
            <div>
                <div style="font-size: 42px; font-weight: 900; color: var(--green);" data-count="<?= $totalReviews ?>"><?= number_format($totalReviews) ?></div>
                <div style="font-size: 12px; color: #888; letter-spacing: 2px; margin-top: 6px; text-transform: uppercase;">Community Reviews</div>
            </div>
            <div>
                <div style="font-size: 42px; font-weight: 900; color: #ffd700;">
                    <?= $avgRating > 0 ? number_format($avgRating, 1) . ' ★' : '—' ?>
                </div>
                <div style="font-size: 12px; color: #888; letter-spacing: 2px; margin-top: 6px; text-transform: uppercase;">Average Rating</div>
            </div>
        </div>
    </section>

    <!-- LATEST REVIEWS FROM DB -->
    <?php if (!empty($latestReviews)): ?>
    <section style="padding: 60px 7%;">
        <div style="max-width: 1100px; margin: 0 auto;">
            <div class="section-title" style="margin-bottom: 35px;">
                <span></span>
                <h2>WHAT OUR COMMUNITY <strong>SAYS</strong></h2>
                <span></span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                <?php foreach ($latestReviews as $rev): ?>
                    <div style="background: #0d0d0d; border: 1px solid #222; border-radius: 12px; padding: 22px; display: flex; flex-direction: column; gap: 10px; transition: border-color .3s;" onmouseover="this.style.borderColor='var(--green)'" onmouseout="this.style.borderColor='#222'">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="avatar cust-av" style="width: 36px; height: 36px; font-size: 14px;">
                                    <?= strtoupper(substr($rev["username"], 0, 2)) ?>
                                </div>
                                <strong style="color: #fff;"><?= e($rev["username"]) ?></strong>
                            </div>
                            <span style="color: #ffd700; font-size: 16px; letter-spacing: 2px;">
                                <?= str_repeat("★", (int)$rev["rating"]) . str_repeat("☆", 5 - (int)$rev["rating"]) ?>
                            </span>
                        </div>
                        <p style="color: #bbb; line-height: 1.6; margin: 0; font-size: 14px;"><?= e($rev["comment"]) ?></p>
                        <small style="color: #555;"><?= date("M d, Y", strtotime($rev["created_at"])) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="text-align: center; margin-top: 30px;">
                <a href="reviews.php" class="hero-btn outline-btn">SEE ALL REVIEWS &rarr;</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer style="padding: 40px 7%; background: #070707; border-top: 1px solid #1a1a1a; text-align: center; color: #777; font-size: 13px;">
        <p>&copy; <?= date("Y") ?> HM Café. All rights reserved. • Coffee • Connect • Enjoy</p>
    </footer>
</div>

<script src="script.js"></script>
</body>
</html>
