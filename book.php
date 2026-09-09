<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/config/functions.php";

$flash = get_flash();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_login("book.php");

    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        flash("error", "Your session expired. Please refresh and try again.");
        header("Location: book.php");
        exit;
    }

    $userId = (int)$_SESSION["user_id"];
    $guests = max(1, filter_input(INPUT_POST, "guests", FILTER_VALIDATE_INT) ?: 2);
    $date = trim($_POST["booking_date"] ?? "");
    $time = trim($_POST["booking_time"] ?? "");
    $notes = trim($_POST["notes"] ?? "");

    if ($date === "" || $time === "") {
        flash("error", "Please provide a valid reservation date and time.");
    } else {
        $stmt = $conn->prepare("INSERT INTO appointments (user_id, guests, booking_date, booking_time, notes, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("iisss", $userId, $guests, $date, $time, $notes);
        if ($stmt->execute()) {
            flash("success", "Table reservation request for {$guests} guest(s) on {$date} at {$time} submitted! We will confirm your table shortly.");
        } else {
            flash("error", "Database error: Could not book table.");
        }
        $stmt->close();
    }
    header("Location: book.php");
    exit;
}

// Fetch user's reservations if logged in
$userBookings = [];
if (is_logged_in()) {
    $uid = (int)$_SESSION["user_id"];
    $bStmt = $conn->prepare("SELECT appointment_id, guests, booking_date, booking_time, notes, status, created_at FROM appointments WHERE user_id = ? ORDER BY booking_date DESC, booking_time DESC");
    $bStmt->bind_param("i", $uid);
    $bStmt->execute();
    $userBookings = $bStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $bStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Book a Table & Appointments</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="innovation.css">
</head>
<body>

<div class="page">
    <?php include __DIR__ . "/layout/navigation.php"; ?>

    <section class="order-page" style="padding-top: 110px; min-height: 80vh; align-items: flex-start;">
        <div style="max-width: 850px; width: 100%; margin: 0 auto;">
            <div class="section-title">
                <span></span>
                <h2>BOOK A <strong>TABLE / APPOINTMENT</strong></h2>
                <span></span>
            </div>
            <p style="text-align: center; color: #888; max-width: 550px; margin: -10px auto 30px; font-size: 14px;">
                Reserve a cozy spot for meetings, coffee dates, study sessions, or celebrations.
            </p>

            <?php if ($flash): ?>
                <div class="form-message <?= e($flash["type"]) ?>"><?= e($flash["message"]) ?></div>
            <?php endif; ?>

            <div class="admin-panel-card" style="margin-bottom: 30px;">
                <h2>📅 Table Reservation Form</h2>
                <form method="post" action="book.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <div class="create-form-grid">
                        <div class="field-group">
                            <label>Number of Guests *</label>
                            <input type="number" name="guests" min="1" max="30" value="2" required>
                        </div>
                        <div class="field-group">
                            <label>Date *</label>
                            <input type="date" name="booking_date" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="field-group">
                            <label>Preferred Time *</label>
                            <input type="time" name="booking_time" required>
                        </div>
                        <div class="field-group">
                            <label>Special Requests / Seating Notes</label>
                            <input type="text" name="notes" placeholder="e.g. Near power outlet, window seat">
                        </div>
                    </div>

                    <div style="margin-top: 20px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="hero-btn green-btn" style="border: none; cursor: pointer;">
                            📅 CONFIRM TABLE RESERVATION
                        </button>
                    </div>
                </form>
            </div>

            <?php if (is_logged_in() && !empty($userBookings)): ?>
                <div class="admin-panel-card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #222;">
                        <h3 style="margin: 0; font-size: 16px; color: #fff;">My Table Reservations</h3>
                    </div>
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>RESERVATION #</th>
                                <th>GUESTS</th>
                                <th>DATE &amp; TIME</th>
                                <th>NOTES</th>
                                <th>STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userBookings as $b): ?>
                                <tr>
                                    <td><strong style="color: var(--green);">#<?= str_pad((string)$b["appointment_id"], 4, "0", STR_PAD_LEFT) ?></strong></td>
                                    <td><?= (int)$b["guests"] ?> Guest(s)</td>
                                    <td><?= date("M d, Y", strtotime($b["booking_date"])) ?> · <?= date("g:i A", strtotime($b["booking_time"])) ?></td>
                                    <td style="color: #888; font-size: 12px;"><?= e($b["notes"] ?: "None") ?></td>
                                    <td>
                                        <span class="status-badge <?= strtolower(e($b["status"])) ?>">
                                            <?= e($b["status"]) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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
