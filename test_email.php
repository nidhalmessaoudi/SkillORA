<?php

/**
 * Test Email Sending
 * This script tests if email sending is working properly
 */

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

echo "=== SkillHarbor Email Test ===\n\n";

// Get MAILER_DSN from environment
$mailerDsn = $_ENV['MAILER_DSN'] ?? null;
$fromAddress = $_ENV['MAILER_FROM_ADDRESS'] ?? 'noreply@skillharbor.com';

if (!$mailerDsn) {
    die("ERROR: MAILER_DSN not found in .env file\n");
}

echo "Configuration:\n";
echo "- MAILER_DSN: " . $mailerDsn . "\n";
echo "- FROM ADDRESS: " . $fromAddress . "\n\n";

// Prompt for test email address
echo "Enter your email address to receive a test email: ";
$testEmail = trim(fgets(STDIN));

if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    die("ERROR: Invalid email address\n");
}

echo "\nAttempting to send test email to: $testEmail\n";
echo "Please wait...\n\n";

try {
    // Create transport from DSN
    $transport = Transport::fromDsn($mailerDsn);
    
    // Create mailer
    $mailer = new Mailer($transport);
    
    // Create email
    $email = (new Email())
        ->from($fromAddress)
        ->to($testEmail)
        ->subject('SkillHarbor - Email Test')
        ->html('
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9;">
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                        <h1 style="color: white; margin: 0;">✅ Email Test Successful!</h1>
                    </div>
                    <div style="background: white; padding: 30px; border-radius: 0 0 10px 10px;">
                        <h2 style="color: #667eea;">Great News!</h2>
                        <p>If you\'re reading this, it means your SkillHarbor email system is working correctly! 🎉</p>
                        <p><strong>Test Details:</strong></p>
                        <ul>
                            <li>From: ' . $fromAddress . '</li>
                            <li>To: ' . $testEmail . '</li>
                            <li>Time: ' . date('Y-m-d H:i:s') . '</li>
                            <li>Transport: SMTP (Gmail)</li>
                        </ul>
                        <p style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #999; font-size: 12px;">
                            This is an automated test email from SkillHarbor Platform
                        </p>
                    </div>
                </div>
            </body>
            </html>
        ')
        ->text('Email Test Successful! Your SkillHarbor email system is working correctly.');
    
    // Send email
    $mailer->send($email);
    
    echo "✅ SUCCESS! Test email sent successfully!\n";
    echo "Please check your inbox (and spam folder) for the test email.\n";
    echo "\nIf you don't receive it within a few minutes, there might be an issue with:\n";
    echo "- Gmail app password (make sure it's correct)\n";
    echo "- Gmail account settings (2FA must be enabled)\n";
    echo "- Firewall blocking port 587\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: Failed to send email\n";
    echo "Error message: " . $e->getMessage() . "\n";
    echo "\nCommon solutions:\n";
    echo "1. Make sure you're using a Gmail App Password (not your regular password)\n";
    echo "2. Enable 2-Factor Authentication on your Gmail account\n";
    echo "3. Generate a new App Password at: https://myaccount.google.com/apppasswords\n";
    echo "4. Check if your antivirus/firewall is blocking port 587\n";
}

echo "\n";
