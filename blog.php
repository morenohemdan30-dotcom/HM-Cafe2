<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/config/functions.php";

$filter = trim($_GET["category"] ?? "all");

$events = [
    [
        "id" => 1,
        "title" => "LATTE ART MASTERCLASS",
        "category" => "workshop",
        "tag" => "WORKSHOP",
        "day" => "15",
        "month" => "OCT",
        "time" => "2:00 PM – 5:00 PM",
        "instructor" => "Head Barista Marco",
        "fee" => "₱350 (Includes Milk Kit & 2 Free Drinks)",
        "image" => "assets/gallery-02.png",
        "desc" => "Master the fundamentals of milk texturing, silky microfoam, and free-pour designs including rosettas, hearts, and multi-tier tulips."
    ],
    [
        "id" => 2,
        "title" => "WEEKEND ACOUSTIC SESSIONS",
        "category" => "music",
        "tag" => "LIVE MUSIC",
        "day" => "22",
        "month" => "OCT",
        "time" => "6:30 PM – 9:30 PM",
        "instructor" => "The Bean Harmonics Acoustic Duo",
        "fee" => "Free Admission (Table reservation recommended)",
        "image" => "assets/gallery-01.png",
        "desc" => "Unwind on Saturday night with relaxing acoustic indie, pop, and bossa nova melodies while enjoying our signature iced blends and pastries."
    ],
    [
        "id" => 3,
        "title" => "COFFEE & PASTRY CUPPING",
        "category" => "tasting",
        "tag" => "TASTING",
        "day" => "29",
        "month" => "OCT",
        "time" => "3:00 PM – 5:30 PM",
        "instructor" => "Master Roaster Elena",
        "fee" => "₱250 (4 Single-Origin Coffees + Pastry Flight)",
        "image" => "assets/gallery-03.png",
        "desc" => "Experience a guided coffee tasting session comparing single-origin beans from Benguet, Bukidnon, Ethiopia, and Colombia paired with artisan cakes."
    ],
    [
        "id" => 4,
        "title" => "MANUAL POUR-OVER BOOTCAMP",
        "category" => "workshop",
        "tag" => "WORKSHOP",
        "day" => "05",
        "month" => "NOV",
        "time" => "10:00 AM – 1:00 PM",
        "instructor" => "Specialty Brewer Ken",
        "fee" => "₱300 (Includes 250g Bean Bag)",
        "image" => "assets/gallery-05.png",
        "desc" => "Learn the science of manual coffee extraction using V60, Chemex, French Press, and Aeropress with ideal grind sizes and water temperatures."
    ],
    [
        "id" => 5,
        "title" => "OPEN MIC POETRY & STORIES",
        "category" => "community",
        "tag" => "COMMUNITY",
        "day" => "12",
        "month" => "NOV",
        "time" => "7:00 PM – 10:00 PM",
        "instructor" => "Dumaguete Writers Collective",
        "fee" => "Free Entry (Performers get a free chocolate chip cookie)",
        "image" => "assets/gallery-04.png",
        "desc" => "A cozy evening celebrating local spoken word poetry, short stories, and music in our dimly lit, ambient lounge area."
    ],
    [
        "id" => 6,
        "title" => "BOARD GAMES & COFFEE SUNDAY",
        "category" => "community",
        "tag" => "SPECIAL",
        "day" => "19",
        "month" => "NOV",
        "time" => "1:00 PM – 6:00 PM",
        "instructor" => "HM Café Community Team",
        "fee" => "Free Play • 15% OFF on all Cold Brews",
        "image" => "assets/gallery-06.png",
        "desc" => "Bring your friends or meet fellow coffee enthusiasts for casual board game tournaments including Catan, Scrabble, Chess, and Exploding Kittens."
    ]
];

$filteredEvents = [];
foreach ($events as $ev) {
    if ($filter === "all" || $filter === "" || $ev["category"] === $filter) {
        $filteredEvents[] = $ev;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Events, Workshops &amp; Community</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="innovation.css">
    <style>
        .event-filter-bar {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin: 0 auto 40px;
            max-width: 800px;
        }
        .event-info-line {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #aaa;
            margin-bottom: 6px;
        }
        .event-info-line span.icon {
            color: var(--green);
            font-size: 14px;
        }
        .journal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            max-width: 1200px;
            margin: 30px auto 0;
        }
        .journal-card {
            background: #0b0b0b;
            border: 1px solid #222;
            border-radius: 14px;
            padding: 24px;
            transition: transform .3s, border-color .3s;
        }
        .journal-card:hover {
            transform: translateY(-5px);
            border-color: var(--green);
        }
        .journal-tag {
            color: var(--green);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

<div class="page">
    <?php include __DIR__ . "/layout/navigation.php"; ?>

    <section class="events-section" style="padding-top: 120px; min-height: 80vh;">
        <div class="events-heading">
            <div class="section-label">COMMUNITY HAPPENINGS</div>
            <h2>SPECIAL EVENTS &amp; <span>WORKSHOPS</span></h2>
            <p>Join our community gatherings, live acoustic nights, barista masterclasses, and coffee cupping sessions.</p>
        </div>

        <!-- CATEGORY FILTERS -->
        <div class="event-filter-bar">
            <a href="blog.php?category=all" class="admin-filter-tab <?= ($filter === 'all' || $filter === '') ? 'active' : '' ?>">ALL EVENTS (<?= count($events) ?>)</a>
            <a href="blog.php?category=workshop" class="admin-filter-tab <?= ($filter === 'workshop') ? 'active' : '' ?>">☕ WORKSHOPS</a>
            <a href="blog.php?category=music" class="admin-filter-tab <?= ($filter === 'music') ? 'active' : '' ?>">🎵 LIVE MUSIC</a>
            <a href="blog.php?category=tasting" class="admin-filter-tab <?= ($filter === 'tasting') ? 'active' : '' ?>">🍰 TASTINGS</a>
            <a href="blog.php?category=community" class="admin-filter-tab <?= ($filter === 'community') ? 'active' : '' ?>">👥 COMMUNITY</a>
        </div>

        <!-- EVENTS GRID -->
        <div class="events-container">
            <?php if (empty($filteredEvents)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 50px 20px; color: #777;">
                    <p>No events found in this category right now. Check back soon!</p>
                </div>
            <?php else: ?>
                <?php foreach ($filteredEvents as $ev): ?>
                    <div class="event-card">
                        <div class="event-image">
                            <img src="<?= e($ev["image"]) ?>" alt="<?= e($ev["title"]) ?>" onerror="this.src='assets/hero-coffee.png'">
                            <div class="event-date">
                                <strong><?= e($ev["day"]) ?></strong>
                                <small><?= e($ev["month"]) ?></small>
                            </div>
                        </div>
                        <div class="event-content">
                            <span class="event-tag"><?= e($ev["tag"]) ?></span>
                            <h3><?= e($ev["title"]) ?></h3>
                            <p><?= e($ev["desc"]) ?></p>

                            <div class="event-info">
                                <div class="event-info-line">
                                    <span class="icon">⏰</span>
                                    <span><?= e($ev["time"]) ?></span>
                                </div>
                                <div class="event-info-line">
                                    <span class="icon">👤</span>
                                    <span><?= e($ev["instructor"]) ?></span>
                                </div>
                                <div class="event-info-line">
                                    <span class="icon">🎟️</span>
                                    <span style="color:#ffd700; font-weight:700;"><?= e($ev["fee"]) ?></span>
                                </div>
                            </div>

                            <a href="book.php" class="event-button">
                                📅 RESERVE TABLE / SPOT <span>→</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- COFFEE JOURNAL & STORIES -->
        <div style="max-width: 1200px; margin: 80px auto 0; padding-top: 50px; border-top: 1px solid #222;">
            <div class="section-title">
                <span></span>
                <h2>THE COFFEE <strong>JOURNAL &amp; STORIES</strong></h2>
                <span></span>
            </div>
            <p style="text-align: center; color: #888; max-width: 600px; margin: -10px auto 30px; font-size: 14px;">
                Learn about brewing techniques, bean origins, and our commitment to sustainable sourcing.
            </p>

            <div class="journal-grid">
                <article class="journal-card">
                    <span class="journal-tag">BREWING GUIDE</span>
                    <h3 style="color:#fff; font-size:18px; margin:10px 0 12px;">How to Brew the Perfect Pour-Over at Home</h3>
                    <p style="color:#888; font-size:13px; line-height:1.6; margin-bottom:18px;">
                        Discover the golden 1:15 coffee-to-water ratio, bloom techniques, and why pour speed determines acidity and sweetness.
                    </p>
                    <a href="shop.php" style="color:var(--green); font-size:12px; font-weight:700;">BROWSE WHOLE BEANS →</a>
                </article>

                <article class="journal-card">
                    <span class="journal-tag">BEAN ORIGIN</span>
                    <h3 style="color:#fff; font-size:18px; margin:10px 0 12px;">Philippine Single-Origin: Benguet &amp; Bukidnon</h3>
                    <p style="color:#888; font-size:13px; line-height:1.6; margin-bottom:18px;">
                        A deep dive into high-altitude mountain farms in the Cordilleras and Mindanao producing specialty grade Arabica beans.
                    </p>
                    <a href="about.php" style="color:var(--green); font-size:12px; font-weight:700;">OUR ETHICAL SOURCING →</a>
                </article>

                <article class="journal-card">
                    <span class="journal-tag">ROAST PROFILES</span>
                    <h3 style="color:#fff; font-size:18px; margin:10px 0 12px;">Light vs. Medium vs. Dark Roasts: What's the Difference?</h3>
                    <p style="color:#888; font-size:13px; line-height:1.6; margin-bottom:18px;">
                        Understand how roast duration influences floral notes, body, caramelization, and overall caffeine content.
                    </p>
                    <a href="shop.php" style="color:var(--green); font-size:12px; font-weight:700;">EXPLORE MENU ITEMS →</a>
                </article>
            </div>
        </div>

        <!-- EVENTS FOOTER STRIP -->
        <div class="events-footer">
            <div>
                <strong>Host Your Private <span>Event or Celebration</span></strong>
                <p>HM Café is available for private birthday bookings, photography sessions, and corporate meetups.</p>
            </div>
            <a href="book.php" class="events-contact-button">
                <span>📅</span> BOOK PRIVATE EVENT
            </a>
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
