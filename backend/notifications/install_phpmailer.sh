#!/bin/bash
# Install PHPMailer for Polomolok Water District GIS
# Run this script from the project root directory

echo "Installing PHPMailer..."

# Check if composer is available
if command -v composer &> /dev/null; then
    composer require phpmailer/phpmailer --no-interaction
    echo "PHPMailer installed via Composer."
else
    echo "Composer not found. Installing manually..."
    mkdir -p vendor/phpmailer/phpmailer/src

    # Try to download via curl
    BASE="https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src"
    FILES=("PHPMailer.php" "SMTP.php" "Exception.php" "OAuth.php")

    for f in "${FILES[@]}"; do
        curl -sL "$BASE/$f" -o "vendor/phpmailer/phpmailer/src/$f"
        if [ $? -eq 0 ]; then
            echo "  Downloaded: $f"
        else
            echo "  FAILED: $f - please download manually from https://github.com/PHPMailer/PHPMailer"
        fi
    done

    # Create minimal autoload
    cat > vendor/autoload.php << 'AUTOLOAD'
<?php
// Minimal autoloader for PHPMailer
spl_autoload_register(function ($class) {
    $classMap = [
        'PHPMailer\\PHPMailer\\PHPMailer'  => __DIR__ . '/phpmailer/phpmailer/src/PHPMailer.php',
        'PHPMailer\\PHPMailer\\SMTP'       => __DIR__ . '/phpmailer/phpmailer/src/SMTP.php',
        'PHPMailer\\PHPMailer\\Exception'  => __DIR__ . '/phpmailer/phpmailer/src/Exception.php',
        'PHPMailer\\PHPMailer\\OAuth'      => __DIR__ . '/phpmailer/phpmailer/src/OAuth.php',
    ];
    if (isset($classMap[$class]) && file_exists($classMap[$class])) {
        require_once $classMap[$class];
    }
});
AUTOLOAD
    echo "Created minimal autoloader."
fi

echo ""
echo "IMPORTANT: Edit backend/notifications/send_email.php and set:"
echo "  MAIL_USERNAME  = your Gmail address"
echo "  MAIL_PASSWORD  = your Gmail App Password"
echo "  (See: https://myaccount.google.com/apppasswords)"
echo ""
echo "Done!"