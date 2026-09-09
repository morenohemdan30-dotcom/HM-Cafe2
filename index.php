<?php
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/config/functions.php";

$contactError = "";
$contactSuccess = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["form_type"] ?? "") === "contact") {
    // Step 1: Collect & trim raw input (Slides 5, 7)
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $subject = trim($_POST["subject"] ?? "");
    $message = trim($_POST["message"] ?? "");

    // Step 2: Validate fields (Slides 6, 8)
    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        $contactError = "Your session expired. Please refresh and try again.";
    } elseif (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $contactError = "Please fill in all contact fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactError = "Please enter a valid email address.";
    } else {
        // Step 3: PDO INSERT with named placeholders (Slide 10)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO contact_messages (name, email, subject, message) 
                VALUES (:name, :email, :subject, :message)
            ");
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':subject', $subject);
            $stmt->bindValue(':message', $message);
            
            if ($stmt->execute()) {
                $contactSuccess = "Thank you! Your message has been sent successfully.";
            } else {
                $contactError = "Unable to send your message right now. Please try again.";
            }
        } catch (PDOException $e) {
            $contactError = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HM Café | Coffee Connect Enjoy</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="innovation.css">
</head>

<body>

    <div class="page">

        <!-- NAVIGATION -->
        <?php include __DIR__ . "/layout/navigation.php"; ?>

        <!-- HOME / HERO -->
        <section id="home" class="hero">
            <div class="hero-content">
                <div class="hero-title">
                    <h1>GOOD COFFEE</h1>
                    <h1>GOOD VIBES</h1>
                    <h1 class="green-text">GOOD MOMENTS</h1>
                </div>
                <p class="hero-description">
                    Savor your favorite coffee enjoy delecious treats, and make every moment special at HM Café
                </p>
                <div class="hero-buttons">
                    <a href="#menu" class="hero-btn green-btn">VIEW MENU</a>
                    <a href="#contact" class="hero-btn outline-btn"> VISIT US</a>
                </div>
            </div>

            <div class="hero-image">
                <div class="hero-circle"></div>
                <img src="assets/hero-coffee.png" alt="HM Café Cup & Coffee Beans">
            </div>
        </section>

        <!-- FEATURES -->
        <section class="features">
            <div class="feature">
                <div class="feature-icon">☕</div>
                <div>
                    <h3>PREMIUM COFFEE</h3>
                    <p>Made from<br>quality beans</p>
                </div>
            </div>

            <div class="feature">
                <div class="feature-icon">🪑</div>
                <div>
                    <h3>COZY PLACE</h3>
                    <p>Comfortable<br>and relaxing</p>
                </div>
            </div>

            <div class="feature">
                <div class="feature-icon">💚</div>
                <div>
                    <h3>FRESH &amp; ORGANIC</h3>
                    <p>Fresh organic<br>ingredients</p>
                </div>
            </div>

            <div class="feature">
                <div class="feature-icon">⚡</div>
                <div>
                    <h3>FAST SERVICE</h3>
                    <p>Quick &amp; friendly<br>service</p>
                </div>
            </div>
        </section>

        <!-- POPULAR MENU -->
        <section id="menu" class="menu-section">
            <div class="section-title">
                <span></span>
                <h2>POPULAR <strong>MENU</strong></h2>
                <span></span>
            </div>

            <div class="menu-grid">
                <!-- ICED COFFEE -->
                <a href="shop.php?product=1" class="menu-card">
                    <img src="assets/menu-iced-coffee.png" alt="Iced Coffee">
                    <div class="menu-content">
                        <h3>ICED COFFEE</h3>
                        <p>Smooth and creamy coffee over ice.</p>
                        <div class="price">₱120</div>
                    </div>
                </a>

                <!-- MATCHA -->
                <a href="shop.php?product=2" class="menu-card">
                    <img src="assets/menu-matcha.png" alt="Matcha">
                    <div class="menu-content">
                        <h3>MATCHA</h3>
                        <p>Rich matcha with a creamy twist.</p>
                        <div class="price">₱130</div>
                    </div>
                </a>

                <!-- CHOCOLATE CAKE -->
                <a href="shop.php?product=3" class="menu-card">
                    <img src="assets/menu-cake.png" alt="Chocolate Cake">
                    <div class="menu-content">
                        <h3>CHOCOLATE CAKE</h3>
                        <p>Moist, rich, and perfectly sweet.</p>
                        <div class="price">₱110</div>
                    </div>
                </a>

                <!-- CREAMY PASTA -->
                <a href="shop.php?product=4" class="menu-card">
                    <img src="assets/menu-pasta.png" alt="Creamy Pasta">
                    <div class="menu-content">
                        <h3>CREAMY PASTA</h3>
                        <p>Creamy, savory, and satisfying.</p>
                        <div class="price">₱150</div>
                    </div>
                </a>
            </div>
        </section>

        <!-- GALLERY -->
        <section id="gallery" class="gallery-section">
            <div class="section-title gallery-title">
                <span></span>
                <h2>Café <strong>GALLERY</strong></h2>
                <span></span>
            </div>

            <div class="gallery-grid">
                <img src="assets/gallery-01.png" alt="Cozy Interior">
                <img src="assets/gallery-02.png" alt="Garden Seating">
                <img src="assets/gallery-03.png" alt="Good Coffee Good Vibes Good Life Neon Lounge">
                <img src="assets/gallery-04.png" alt="Dining Tables">
                <img src="assets/gallery-05.png" alt="Bar Counter & Desserts">
                <img src="assets/gallery-06.png" alt="Outdoor Patio">
                <img src="assets/gallery-07.png" alt="Evening Lights">
            </div>
        </section>

        <!-- EVENTS & WORKSHOPS SECTION -->
        <section id="events" class="events-section">
            <div class="section-title">
                <span></span>
                <h2>SPECIAL <strong>EVENTS &amp; WORKSHOPS</strong></h2>
                <span></span>
            </div>
            <p style="text-align: center; color: #888; max-width: 600px; margin: -10px auto 35px; font-size: 14px;">
                Join our community gatherings, live acoustic nights, and barista masterclasses.
            </p>

            <div class="events-container">
                <!-- EVENT 1: LATTE ART -->
                <div class="event-card">
                    <div class="event-image">
                        <img src="assets/gallery-02.png" alt="Latte Art Workshop" onerror="this.src='assets/hero-coffee.png'">
                        <div class="event-date">
                            <strong>15</strong>
                            <small>OCT</small>
                        </div>
                    </div>
                    <div class="event-content">
                        <span class="event-tag">WORKSHOP</span>
                        <h3>LATTE ART MASTERCLASS</h3>
                        <p>Learn milk steaming techniques, silky microfoam, and pouring free-pour rosettas with Head Barista Marco.</p>

                        <div class="event-info">
                            <div><span>⏰</span> 2:00 PM – 5:00 PM</div>
                            <div><span>👤</span> Head Barista Marco</div>
                            <div><span>🎟️</span> <strong style="color:#ffd700;">₱350 (Includes Milk Kit &amp; 2 Drinks)</strong></div>
                        </div>

                        <a href="book.php" class="event-button">
                            📅 RESERVE SPOT <span>→</span>
                        </a>
                    </div>
                </div>

                <!-- EVENT 2: ACOUSTIC SESSIONS -->
                <div class="event-card">
                    <div class="event-image">
                        <img src="assets/gallery-01.png" alt="Weekend Acoustic Sessions" onerror="this.src='assets/hero-coffee.png'">
                        <div class="event-date">
                            <strong>22</strong>
                            <small>OCT</small>
                        </div>
                    </div>
                    <div class="event-content">
                        <span class="event-tag">LIVE MUSIC</span>
                        <h3>WEEKEND ACOUSTIC SESSIONS</h3>
                        <p>Enjoy live acoustic indie and jazz performances while sipping your favorite handcrafted iced brews.</p>

                        <div class="event-info">
                            <div><span>⏰</span> 6:30 PM – 9:30 PM</div>
                            <div><span>👤</span> The Bean Harmonics</div>
                            <div><span>🎟️</span> <strong style="color:#ffd700;">Free Entry (Table booking advised)</strong></div>
                        </div>

                        <a href="book.php" class="event-button">
                            📅 RESERVE TABLE <span>→</span>
                        </a>
                    </div>
                </div>

                <!-- EVENT 3: CUPPING -->
                <div class="event-card">
                    <div class="event-image">
                        <img src="assets/gallery-03.png" alt="Coffee & Pastry Cupping" onerror="this.src='assets/hero-coffee.png'">
                        <div class="event-date">
                            <strong>29</strong>
                            <small>OCT</small>
                        </div>
                    </div>
                    <div class="event-content">
                        <span class="event-tag">TASTING</span>
                        <h3>COFFEE &amp; PASTRY CUPPING</h3>
                        <p>Guided tasting comparing single-origin beans from Benguet, Bukidnon, and Ethiopia paired with cakes.</p>

                        <div class="event-info">
                            <div><span>⏰</span> 3:00 PM – 5:30 PM</div>
                            <div><span>👤</span> Master Roaster Elena</div>
                            <div><span>🎟️</span> <strong style="color:#ffd700;">₱250 (4 Coffees + Pastry Flight)</strong></div>
                        </div>

                        <a href="book.php" class="event-button">
                            📅 RESERVE SPOT <span>→</span>
                        </a>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 35px;">
                <a href="blog.php" class="hero-btn green-btn">VIEW ALL 6 UPCOMING EVENTS &amp; BLOG →</a>
            </div>
        </section>

        <!-- CONTACT FORM (VISIT US) -->
        <section id="contact" class="innovative-contact-section reveal">
            <div class="section-title">
                <span></span>
                <h2>VISIT <strong>US &amp; GET IN TOUCH</strong></h2>
                <span></span>
            </div>

            <?php if ($contactSuccess): ?>
                <div class="form-message success" style="max-width:800px; margin:0 auto 25px;"><?= e($contactSuccess) ?></div>
            <?php endif; ?>
            <?php if ($contactError): ?>
                <div class="form-message error" style="max-width:800px; margin:0 auto 25px;"><?= e($contactError) ?></div>
            <?php endif; ?>

            <div class="innovative-contact-grid">
                <!-- Left: Store Experience & Details -->
                <div class="store-info-card reveal-left">
                    <div>
                        <div class="live-badge">
                            <span class="pulse-dot"></span>
                            <span>OPEN NOW · 7:00 AM – 9:00 PM</span>
                        </div>

                        <div class="store-info-header">
                            <div class="store-sublabel">☕ YOUR LOCAL COFFEE SANCTUARY</div>
                            <h3>EXPERIENCE <span>HM CAFÉ</span></h3>
                            <div class="small-green-line" style="margin: 8px 0 14px;"></div>
                            <p>Drop by for freshly roasted artisanal brews, comfortable co-working spaces, and warm neighborhood hospitality.</p>
                        </div>

                        <div class="contact-meta-list">
                            <div class="contact-meta-item">
                                <div class="contact-meta-icon">📍</div>
                                <div class="contact-meta-content">
                                    <strong>Store Location</strong>
                                    <span>123 Coffee Street, Bagacay, Dumaguete City</span>
                                </div>
                            </div>

                            <div class="contact-meta-item">
                                <div class="contact-meta-icon">📞</div>
                                <div class="contact-meta-content">
                                    <strong>Phone & Hotline</strong>
                                    <a href="tel:09353652127">+63 935 365 2127</a>
                                </div>
                            </div>

                            <div class="contact-meta-item">
                                <div class="contact-meta-icon">✉️</div>
                                <div class="contact-meta-content">
                                    <strong>Email Inquiries</strong>
                                    <a href="mailto:morenohemdan30@gmail.com">morenohemdan30@gmail.com</a>
                                </div>
                            </div>

                            <div class="contact-meta-item">
                                <div class="contact-meta-icon">⏰</div>
                                <div class="contact-meta-content">
                                    <strong>Service Hours</strong>
                                    <span>Monday – Sunday: 7:00 AM – 9:00 PM</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="store-quick-actions">
                        <a href="book.php" class="quick-action-pill">
                            <span>📅</span> BOOK TABLE
                        </a>
                        <a href="shop.php" class="quick-action-pill">
                            <span>☕</span> ORDER ONLINE
                        </a>
                        <a href="https://maps.google.com/?q=Dumaguete+City" target="_blank" class="quick-action-pill">
                            <span>🗺️</span> DIRECTIONS
                        </a>
                    </div>
                </div>

                <!-- Right: Innovative Glassmorphism Message Form -->
                <div class="contact-form-card reveal-right">
                    <div class="form-header-innovative">
                        <div class="store-sublabel">💬 WE'D LOVE TO HEAR FROM YOU</div>
                        <h3>SEND US A <span>MESSAGE</span></h3>
                        <div class="small-green-line" style="margin: 8px 0 14px;"></div>
                        <p>Have questions about our artisan brews, catering, or table reservations? Drop us a note below.</p>
                    </div>

                    <form method="POST" action="index.php#contact">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="form_type" value="contact">

                        <div class="innovative-form-row">
                            <div class="innovative-input-group">
                                <label>Your Name <span>*</span></label>
                                <div class="input-with-icon">
                                    <span class="field-icon">👤</span>
                                    <input type="text" name="name" required class="innovative-input" placeholder="e.g. Juan dela Cruz">
                                </div>
                            </div>

                            <div class="innovative-input-group">
                                <label>Email Address <span>*</span></label>
                                <div class="input-with-icon">
                                    <span class="field-icon">✉️</span>
                                    <input type="email" name="email" required class="innovative-input" placeholder="e.g. juan@example.com">
                                </div>
                            </div>
                        </div>

                        <div class="innovative-input-group">
                            <label>Subject <span>*</span></label>
                            <div class="input-with-icon">
                                <span class="field-icon">📝</span>
                                <input type="text" name="subject" required class="innovative-input" placeholder="e.g. Table Booking / Coffee Inquiry">
                            </div>
                        </div>

                        <div class="innovative-input-group">
                            <label>Your Message <span>*</span></label>
                            <textarea name="message" rows="4" required class="innovative-input" placeholder="How can our baristas craft something special for you today?"></textarea>
                        </div>

                        <button type="submit" class="submit-btn-innovative">
                            <span>☕ SEND MESSAGE</span>
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="footer">
            <!-- Col 1: Brand -->
            <div class="footer-brand">
                <img src="assets/logo.png" alt="HM Café Logo">
                <div>
                    <div class="footer-name">HM <span>Café</span></div>
                    <div class="footer-tagline">COFFEE CONNECT ENJOY</div>
                    <p>Thank you for supporting our local cafe</p>
                </div>
            </div>

            <!-- Col 2: Hours -->
            <div class="footer-column">
                <h4>Monday - Friday</h4>
                <p>7:00 AM - 9:00 PM</p>
            </div>

            <!-- Col 3: Quick Links -->
            <div class="footer-column">
                <a href="#home">Home</a>
                <a href="#menu">Menu</a>
                <a href="#about">About Us</a>
                <a href="#gallery">Gallery</a>
                <a href="blog.php">Events</a>
                <a href="#contact">Contact</a>
            </div>

            <!-- Col 4: Contact info -->
            <div class="footer-column contact-footer">
                <p><span>📞</span> 09353652127</p>
                <p><span>✉</span> morenohemdan30@gmail.com</p>
                <p><span>📍</span> 123 Coffee Street Bagacay Dumaguete City</p>
            </div>
        </footer>

    </div>

    <script src="script.js"></script>
</body>
</html>