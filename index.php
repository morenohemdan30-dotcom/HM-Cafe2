<?php
// Forward root XAMPP requests to Symfony's public directory
header("Location: public/");
exit;
