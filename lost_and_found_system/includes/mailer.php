<?php
/**
 * Mailer Helper — PHPMailer via Gmail SMTP
 *
 * Usage:
 *   sendMail($toEmail, $toName, $subject, $htmlBody);
 *
 * Returns true on success, throws Exception on failure.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Load PHPMailer classes (manual install, no Composer)
require_once __DIR__ . '/PHPMailerException.php';
require_once __DIR__ . '/SMTP.php';
require_once __DIR__ . '/PHPMailer.php';

/**
 * Send an HTML email via Gmail SMTP.
 *
 * @param string $toEmail   Recipient email address
 * @param string $toName    Recipient display name
 * @param string $subject   Email subject
 * @param string $htmlBody  HTML email body
 * @param string $textBody  Plain-text fallback (optional)
 * @return bool
 * @throws PHPMailerException
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
{
    $mail = new PHPMailer(true); // true = exceptions enabled

    // Server settings
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS on port 587
    $mail->Port       = SMTP_PORT;

    // Debug: 0 = off, 2 = full (set to 0 in production)
    $mail->SMTPDebug  = (defined('APP_DEBUG') && APP_DEBUG) ? 0 : 0;

    // Sender
    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addReplyTo(SMTP_FROM, SMTP_FROM_NAME);

    // Recipient
    $mail->addAddress($toEmail, $toName);

    // Content
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = $subject;
    $mail->Body    = $htmlBody;
    $mail->AltBody = $textBody ?: strip_tags($htmlBody);

    return $mail->send();
}
