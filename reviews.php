<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/config/functions.php";

$flash = get_flash();

// Handle new review submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_login("reviews.php");

    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        flash("error", "Your session expired. Please refresh and try again.");
        header("Location: reviews.php");
        exit;
    }

    $userId = (int)$_SESSION["user_id"];
    $rating = max(1, min(5, (int)($_POST["rating"] ?? 5)));
    $comment = trim($_POST["comment"] ?? "");

    if ($comment === "") {
        flash("error", "Please write a brief feedback comment.");
    } else {
        $stmt = $conn->prepare("INSERT INTO reviews (user_id, rating, comment) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $userId, $rating, $comment);
        if ($stmt->execute()) {
            flash("success", "Thank you! Your review has been posted.");
        } else {
            flash("error", "Database error: Unable to post review.");
        }
        $stmt->close();
    }
    header("Location: reviews.php");
    exit;
}

// Fetch all reviews
$rQuery = $conn->query("
    SELECT r.review_id, r.rating, r.comment, r.created_at, u.username
    FROM reviews r
    JOIN users u ON u.user_id = r.user_id
    ORDER BY r.created_at DESC, r.review_id DESC
");
$reviews = $rQuery ? $rQuery->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Customer Reviews &amp; Testimonials</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="innovation.css">
    <style>
        .star-rating {
            color: #ffd700;
            font-size: 18px;
            letter-spacing: 2px;
        }
        .review-card {
            background: #0d0d0d;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
    </style>
</head>
<body>

<div class="page">
    <?php include __DIR__ . "/layout/navigation.php"; ?>

    <section class="order-page" style="padding-top: 110px; min-height: 80vh; align-items: flex-start;">
        <div style="max-width: 900px; width: 100%; margin: 0 auto;">
            <div class="section-title">
                <span></span>
                <h2>CUSTOMER <strong>REVIEWS &amp; STORIES</strong></h2>
                <span></span>
            </div>
            <p style="text-align: center; color: #888; max-width: 550px; margin: -10px auto 30px; font-size: 14px;">
                See what our coffee community says about our drinks, handcrafted food, and café ambiance.
            </p>

            <?php if ($flash): ?>
                <div class="form-message <?= e($flash["type"]) ?>"><?= e($flash["message"]) ?></div>
            <?php endif; ?>

            <!-- SUBMIT REVIEW FORM -->
            <div class="admin-panel-card" style="margin-bottom: 30px;">
                <h2>✍️ Leave Your Feedback &amp; Review</h2>
                <?php if (is_logged_in()): ?>
                    <form method="post" action="reviews.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                        <div style="margin-bottom: 15px;">
                            <label style="font-size: 11px; font-weight: 700; color: #aaa; text-transform: uppercase;">Star Rating *</label>
                            <select name="rating" class="admin-select" style="width: 100%; margin-top: 6px;">
                                <option value="5">⭐⭐⭐⭐⭐ (5 Stars - Outstanding)</option>
                                <option value="4">⭐⭐⭐⭐ (4 Stars - Very Good)</option>
                                <option value="3">⭐⭐⭐ (3 Stars - Average)</option>
                                <option value="2">⭐⭐ (2 Stars - Needs Improvement)</option>
                                <option value="1">⭐ (1 Star - Poor)</option>
                            </select>
                        </div>

                        <div class="field-group" style="margin-bottom: 18px;">
                            <label>Your Review / Experience *</label>
                            <textarea name="comment" rows="3" required placeholder="What did you love about your order or visit?" style="background:#0d0d0d; border:1px solid #2e2e2e; color:#fff; padding:12px; border-radius:8px; resize:vertical;"></textarea>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="hero-btn green-btn" style="border: none; cursor: pointer;">
                                ⭐ POST REVIEW
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <p style="color: #aaa;">Please log in or create an account to share your coffee review.</p>
                    <div style="margin-top: 15px;">
                        <a href="login.php?redirect=reviews.php" class="hero-btn green-btn">LOG IN TO REVIEW</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- REVIEWS LIST -->
            <h2 style="font-size: 20px; color: #fff; margin-bottom: 18px;">Community Reviews (<?= count($reviews) ?>)</h2>
            <div style="display: grid; gap: 16px;">
                <?php if (empty($reviews)): ?>
                    <div class="admin-panel-card" style="text-align: center; padding: 40px; color: #666;">
                        <p>No customer reviews yet. Be the first to post a review!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $rev): ?>
                        <div class="review-card">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="avatar cust-av" style="width: 36px; height: 36px; font-size: 14px;">
                                        <?= strtoupper(substr($rev["username"], 0, 2)) ?>
                                    </div>
                                    <strong style="color: #fff;"><?= e($rev["username"]) ?></strong>
                                </div>
                                <div class="star-rating">
                                    <?= str_repeat("★", (int)$rev["rating"]) . str_repeat("☆", 5 - (int)$rev["rating"]) ?>
                                </div>
                            </div>
                            <p style="color: #bbb; line-height: 1.6; margin: 0; font-size: 14px;"><?= e($rev["comment"]) ?></p>
                            <small style="color: #666;"><?= date("M d, Y · h:i A", strtotime($rev["created_at"])) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer style="padding: 40px 7%; background: #070707; border-top: 1px solid #1a1a1a; text-align: center; color: #777; font-size: 13px;">
        <p>&copy; <?= date("Y") ?> HM Café. All rights reserved. • Coffee • Connect • Enjoy</p>
    </footer>
</div>

<script src="script.js"></script>
</body>
</html>
