<?php

/**
 * Email System Diagnostic and Fix
 */

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

echo "=== SkillHarbor Email Diagnostic ===\n\n";

// Load .env file
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

echo "Step 1: Checking .env configuration\n";
echo "-------------------------------------\n";

$mailerDsn = $_ENV['MAILER_DSN'] ?? null;
$fromAddress = $_ENV['MAILER_FROM_ADDRESS'] ?? null;
$fromName = $_ENV['MAILER_FROM_NAME'] ?? null;

echo "MAILER_DSN: " . ($mailerDsn ? '✓ Found' : '✗ NOT FOUND') . "\n";
echo "MAILER_FROM_ADDRESS: " . ($fromAddress ? $fromAddress : '✗ NOT FOUND') . "\n";
echo "MAILER_FROM_NAME: " . ($fromName ? $fromName : '✗ NOT FOUND') . "\n\n";

if (!$mailerDsn || $mailerDsn === 'null://null') {
    echo "❌ PROBLEM FOUND: MAILER_DSN is null or not set!\n\n";
    echo "SOLUTION:\n";
    echo "Open .env file and make sure this line exists and is NOT commented:\n";
    echo "MAILER_DSN=smtp://alarezgui98@gmail.com:YOUR_APP_PASSWORD@smtp.gmail.com:587\n\n";
    exit(1);
}

echo "Step 2: Testing SMTP Connection\n";
echo "--------------------------------\n";

// Parse DSN
if (preg_match('/smtp:\/\/(.+):(.+)@(.+):(\d+)/', $mailerDsn, $matches)) {
    $username = $matches[1];
    $password = $matches[2];
    $host = $matches[3];
    $port = (int)$matches[4];
    
    echo "Host: $host\n";
    echo "Port: $port\n";
    echo "Username: $username\n";
    echo "Password: " . str_repeat('*', strlen($password)) . "\n\n";
    
    // Test connection
    echo "Testing SMTP connection...\n";
    
    $socket = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$socket) {
        echo "❌ FAILED: Cannot connect to SMTP server\n";
        echo "Error: $errstr ($errno)\n\n";
        echo "POSSIBLE CAUSES:\n";
        echo "1. Firewall blocking port $port\n";
        echo "2. Internet connection issue\n";
        echo "3. SMTP server is down\n\n";
        exit(1);
    } else {
        echo "✓ Successfully connected to SMTP server\n";
        fclose($socket);
    }
} else {
    echo "❌ Cannot parse MAILER_DSN format\n";
    exit(1);
}

echo "\nStep 3: Testing Email Sending with Symfony Mailer\n";
echo "---------------------------------------------------\n";

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

echo "Enter your email address to receive a test: ";
$testEmail = trim(fgets(STDIN));

if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    die("❌ Invalid email address\n");
}

echo "\nSending test email to: $testEmail\n";
echo "Please wait...\n\n";

try {
    $transport = Transport::fromDsn($mailerDsn);
    $mailer = new Mailer($transport);
    
    $email = (new Email())
        ->from($fromAddress)
        ->to($testEmail)
        ->subject('SkillHarbor - Email System Test')
        ->html('
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                    <h1 style="margin: 0;">✅ Email Working!</h1>
                </div>
                <div style="background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;">
                    <h2 style="color: #667eea;">Congratulations!</h2>
                    <p>Your SkillHarbor email system is now working correctly.</p>
                    <p><strong>Configuration:</strong></p>
                    <ul>
                        <li>From: ' . htmlspecialchars($fromAddress) . '</li>
                        <li>SMTP Host: ' . htmlspecialchars($host) . '</li>
                        <li>SMTP Port: ' . $port . '</li>
                        <li>Time: ' . date('Y-m-d H:i:s') . '</li>
                    </ul>
                    <p style="margin-top: 30px; color: #999; font-size: 12px; border-top: 1px solid #ddd; padding-top: 20px;">
                        This is an automated test from SkillHarbor Platform
                    </p>
                </div>
            </div>
        ');
    
    $mailer->send($email);
    
    echo "✅ SUCCESS! Email sent successfully!\n\n";
    echo "Please check:\n";
    echo "1. Your inbox: $testEmail\n";
    echo "2. Spam/Junk folder (emails might end up there)\n";
    echo "3. Wait 1-2 minutes for delivery\n\n";
    echo "If you received the email, your system is working! ✓\n";
    echo "Now try registering a new account to test verification emails.\n";
    
} catch (\Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n\n";
    
    $errorMsg = $e->getMessage();
    
    if (strpos($errorMsg, 'Authentication') !== false || strpos($errorMsg, 'Username and Password not accepted') !== false) {
        echo "PROBLEM: Gmail App Password is incorrect or expired\n\n";
        echo "SOLUTION:\n";
        echo "1. Go to: https://myaccount.google.com/apppasswords\n";
        echo "2. Sign in to: $username\n";
        echo "3. Make sure 2-Factor Authentication is enabled\n";
        echo "4. Create a new App Password (name it 'SkillHarbor')\n";
        echo "5. Copy the 16-character password (remove spaces)\n";
        echo "6. Update .env file:\n";
        echo "   MAILER_DSN=smtp://$username:YOUR_NEW_PASSWORD@$host:$port\n\n";
    } elseif (strpos($errorMsg, 'Connection') !== false || strpos($errorMsg, 'timed out') !== false) {
        echo "PROBLEM: Cannot connect to Gmail SMTP server\n\n";
        echo "SOLUTION:\n";
        echo "1. Check your internet connection\n";
        echo "2. Disable firewall/antivirus temporarily\n";
        echo "3. Try a different network\n";
        echo "4. Make sure port $port is not blocked\n\n";
    } else {
        echo "UNKNOWN ERROR\n\n";
        echo "Try:\n";
        echo "1. Generate a fresh Gmail App Password\n";
        echo "2. Check if 2FA is enabled on Gmail account\n";
        echo "3. Make sure you're using the correct email: $username\n\n";
    }
}

echo "\n";
