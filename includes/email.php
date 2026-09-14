<?php
/**
 * Email Utility Class for Dhana Booking System
 * Handles sending emails using Gmail SMTP
 */

require_once __DIR__ . '/../config/email.php';

class EmailService {
    private $smtpHost;
    private $smtpPort;
    private $smtpSecure;
    private $smtpUsername;
    private $smtpPassword;
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        $this->smtpHost = SMTP_HOST;
        $this->smtpPort = SMTP_PORT;
        $this->smtpSecure = SMTP_SECURE;
        $this->smtpUsername = SMTP_USERNAME;
        $this->smtpPassword = SMTP_PASSWORD;
        $this->fromEmail = filter_var(SMTP_FROM_EMAIL, FILTER_VALIDATE_EMAIL) ? SMTP_FROM_EMAIL : SMTP_USERNAME;
        $this->fromName = trim(str_replace(["\r", "\n"], '', SMTP_FROM_NAME));
    }
    
    /** Send through configured SMTP, with PHP mail() as a hosting fallback. */
    public function sendEmail($to, $subject, $htmlBody, $plainTextBody = '') {
        try {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'error' => 'Invalid recipient address'];
            }
            $subject = str_replace(["\r", "\n"], '', (string)$subject);
            // If plain text not provided, strip HTML tags
            if (empty($plainTextBody)) {
                $plainTextBody = strip_tags($htmlBody);
            }
            
            // Create boundary for multipart email
            $boundary = md5(uniqid(time()));
            
            // Headers
            $headers = [];
            $headers[] = "From: {$this->fromName} <{$this->fromEmail}>";
            $headers[] = "Reply-To: {$this->fromEmail}";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
            $headers[] = "X-Mailer: PHP/" . phpversion();
            
            // Message body
            $message = "--{$boundary}\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $plainTextBody . "\r\n\r\n";
            
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $htmlBody . "\r\n\r\n";
            
            $message .= "--{$boundary}--";
            
            $smtpConfigured = filter_var($this->smtpUsername, FILTER_VALIDATE_EMAIL)
                && $this->smtpPassword !== ''
                && strpos($this->smtpPassword, 'your-') !== 0;
            $result = $smtpConfigured
                ? $this->sendViaSmtp($to, $subject, $headers, $message)
                : mail($to, $subject, $message, implode("\r\n", $headers));
            
            if ($result) {
                return ['success' => true, 'message' => 'Email sent successfully'];
            } else {
                error_log("Email sending failed to: {$to}");
                return ['success' => false, 'error' => 'Failed to send email'];
            }
            
        } catch (Throwable $e) {
            error_log("Email error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Email sending failed'];
        }
    }

    private function sendViaSmtp($to, $subject, array $headers, $message) {
        $transport = strtolower($this->smtpSecure) === 'ssl' ? 'ssl' : 'tcp';
        $socket = @stream_socket_client(
            $transport . '://' . $this->smtpHost . ':' . (int)$this->smtpPort,
            $errorNumber,
            $errorMessage,
            15,
            STREAM_CLIENT_CONNECT
        );
        if (!$socket) throw new RuntimeException('SMTP connection failed.');
        stream_set_timeout($socket, 15);

        try {
            $this->expectSmtp($socket, [220]);
            $hostName = preg_replace('/[^A-Za-z0-9.-]/', '', $_SERVER['SERVER_NAME'] ?? 'localhost') ?: 'localhost';
            $this->smtpCommand($socket, 'EHLO ' . $hostName, [250]);

            if (strtolower($this->smtpSecure) === 'tls') {
                $this->smtpCommand($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP encryption failed.');
                }
                $this->smtpCommand($socket, 'EHLO ' . $hostName, [250]);
            }

            $this->smtpCommand($socket, 'AUTH LOGIN', [334]);
            $this->smtpCommand($socket, base64_encode($this->smtpUsername), [334]);
            $this->smtpCommand($socket, base64_encode($this->smtpPassword), [235]);
            $this->smtpCommand($socket, 'MAIL FROM:<' . $this->fromEmail . '>', [250]);
            $this->smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->smtpCommand($socket, 'DATA', [354]);

            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $payload = 'To: <' . $to . ">\r\nSubject: " . $encodedSubject . "\r\n"
                . implode("\r\n", $headers) . "\r\n\r\n" . $message;
            $payload = preg_replace('/(?m)^\./', '..', $payload);
            fwrite($socket, $payload . "\r\n.\r\n");
            $this->expectSmtp($socket, [250]);
            $this->smtpCommand($socket, 'QUIT', [221]);
            return true;
        } finally {
            fclose($socket);
        }
    }

    private function smtpCommand($socket, $command, array $expectedCodes) {
        fwrite($socket, $command . "\r\n");
        return $this->expectSmtp($socket, $expectedCodes);
    }

    private function expectSmtp($socket, array $expectedCodes) {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('SMTP server rejected the request (code ' . $code . ').');
        }
        return $response;
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($to, $userName, $resetToken) {
        $resetLink = RESET_LINK_BASE_URL . '?token=' . urlencode($resetToken);
        $expiryMinutes = RESET_TOKEN_EXPIRY / 60;
        
        $subject = "Password Reset Request - " . SITE_NAME;
        
        // HTML version
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #ff9800, #f57c00); color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
                .button { display: inline-block; padding: 12px 30px; background: #ff9800; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 5px 5px; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🪷 Password Reset Request</h1>
                </div>
                <div class='content'>
                    <p>Dear {$userName},</p>
                    
                    <p>We received a request to reset your password for your Dhana Reservation System account.</p>
                    
                    <p>Click the button below to reset your password:</p>
                    
                    <p style='text-align: center;'>
                        <a href='{$resetLink}' class='button'>Reset Password</a>
                    </p>
                    
                    <p>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; background: #fff; padding: 10px; border: 1px solid #ddd;'>{$resetLink}</p>
                    
                    <div class='warning'>
                        <strong>⚠️ Important:</strong> This link will expire in {$expiryMinutes} minutes.
                    </div>
                    
                    <p>If you didn't request a password reset, please ignore this email. Your password will remain unchanged.</p>
                    
                    <p>May you be well and happy,<br>
                    <strong>Dhana Reservation System Team</strong></p>
                </div>
                <div class='footer'>
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>&copy; " . date('Y') . " Dhana Reservation System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Plain text version
        $plainTextBody = "
Password Reset Request - {$subject}

Dear {$userName},

We received a request to reset your password for your Dhana Reservation System account.

Click the link below to reset your password:
{$resetLink}

IMPORTANT: This link will expire in {$expiryMinutes} minutes.

If you didn't request a password reset, please ignore this email. Your password will remain unchanged.

May you be well and happy,
Dhana Reservation System Team

---
This is an automated email. Please do not reply to this message.
        ";
        
        return $this->sendEmail($to, $subject, $htmlBody, $plainTextBody);
    }

    /**
     * Send booking confirmation email (when booking is created)
     */
    public function sendBookingCreatedEmail($bookingData) {
        $to = $bookingData['user_email'];
        $userName = $bookingData['user_name'];
        $bookingId = $bookingData['booking_id'];
        $dhanaType = $bookingData['dhana_type'];
        $bookingDate = date('F d, Y', strtotime($bookingData['booking_date']));
        $timeSlot = ucfirst(str_replace('_', ' ', $bookingData['time_slot']));
        $amount = number_format($bookingData['amount'], 2);
        $paymentLink = SITE_URL . '/payment.php?booking_id=' . $bookingId;
        $bookedForName = trim((string)($bookingData['booked_for_name'] ?? ''));
        $recipientRowHtml = $bookedForName !== ''
            ? "<div class='detail-row'><span class='detail-label'>Reservation Recipient:</span><span class='detail-value'>" . htmlspecialchars($bookedForName, ENT_QUOTES, 'UTF-8') . "</span></div>"
            : '';
        $recipientRowText = $bookedForName !== '' ? "- Reservation Recipient: {$bookedForName}\n" : '';

        // Get timeout hours for the warning message
        $db = getDB();
        $timeoutSetting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'pending_booking_timeout_hours'");
        $timeoutHours = $timeoutSetting ? (int)$timeoutSetting['setting_value'] : 72;

        $subject = "Dhana Booking Confirmation - Booking #" . $bookingId;

        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
                .booking-details { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e0e0e0; }
                .detail-label { font-weight: bold; color: #666; }
                .detail-value { color: #333; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                .button { display: inline-block; background: #ff9800; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 0.9rem; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🙏 Dhana Booking Created</h1>
                    <p>Your reservation has been successfully created</p>
                </div>
                <div class='content'>
                    <p>Dear {$userName},</p>

                    <p>Thank you for making a dhana offering! Your booking has been created successfully.</p>

                    <div class='booking-details'>
                        <h3 style='margin-top: 0; color: #ff9800;'>📋 Booking Details</h3>
                        <div class='detail-row'>
                            <span class='detail-label'>Booking ID:</span>
                            <span class='detail-value'>#{$bookingId}</span>
                        </div>
                        {$recipientRowHtml}
                        <div class='detail-row'>
                            <span class='detail-label'>Dhana Type:</span>
                            <span class='detail-value'>{$dhanaType}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Date:</span>
                            <span class='detail-value'>{$bookingDate}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Time Slot:</span>
                            <span class='detail-value'>{$timeSlot}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Amount:</span>
                            <span class='detail-value'>LKR {$amount}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Status:</span>
                            <span class='detail-value' style='color: #ffc107;'>⏳ Pending Payment</span>
                        </div>
                    </div>

                    <div class='warning'>
                        <strong>⚠️ Important - Next Steps:</strong><br>
                        Please upload your payment receipt within <strong>{$timeoutHours} hours</strong> to confirm your booking.<br>
                        If payment is not received within this time, your booking will be automatically cancelled.
                    </div>

                    <div style='text-align: center;'>
                        <a href='{$paymentLink}' class='button'>📤 Upload Payment Receipt</a>
                    </div>

                    <p>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; background: #f8f9fa; padding: 10px; border: 1px solid #ddd;'>{$paymentLink}</p>

                    <p>May you be well and happy,<br>
                    <strong>Dhana Reservation System Team</strong></p>
                </div>
                <div class='footer'>
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>&copy; " . date('Y') . " Dhana Reservation System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $plainTextBody = "
Dhana Booking Confirmation - Booking #{$bookingId}

Dear {$userName},

Thank you for making a dhana offering! Your booking has been created successfully.

BOOKING DETAILS:
- Booking ID: #{$bookingId}
{$recipientRowText}- Dhana Type: {$dhanaType}
- Date: {$bookingDate}
- Time Slot: {$timeSlot}
- Amount: LKR {$amount}
- Status: Pending Payment

IMPORTANT - NEXT STEPS:
Please upload your payment receipt within {$timeoutHours} hours to confirm your booking.
If payment is not received within this time, your booking will be automatically cancelled.

Upload payment receipt here:
{$paymentLink}

May you be well and happy,
Dhana Reservation System Team

---
This is an automated email. Please do not reply to this message.
        ";

        return $this->sendEmail($to, $subject, $htmlBody, $plainTextBody);
    }

    /**
     * Send receipt uploaded email
     */
    public function sendReceiptUploadedEmail($bookingData) {
        $to = $bookingData['user_email'];
        $userName = $bookingData['user_name'];
        $bookingId = $bookingData['booking_id'];
        $dhanaType = $bookingData['dhana_type'];
        $bookingDate = date('F d, Y', strtotime($bookingData['booking_date']));

        $subject = "Payment Receipt Received - Booking #" . $bookingId;

        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
                .info-box { background: #e3f2fd; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 0.9rem; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📤 Payment Receipt Received</h1>
                    <p>Booking #{$bookingId}</p>
                </div>
                <div class='content'>
                    <p>Dear {$userName},</p>

                    <p>We have successfully received your payment receipt for your dhana booking.</p>

                    <div class='info-box'>
                        <strong>📋 Booking Information:</strong><br>
                        <strong>Booking ID:</strong> #{$bookingId}<br>
                        <strong>Dhana Type:</strong> {$dhanaType}<br>
                        <strong>Date:</strong> {$bookingDate}<br>
                        <strong>Status:</strong> ⏳ Pending Verification
                    </div>

                    <p><strong>What happens next?</strong></p>
                    <ul>
                        <li>Our staff will verify your payment receipt</li>
                        <li>You will receive a confirmation email once verified</li>
                        <li>This usually takes 24-48 hours</li>
                    </ul>

                    <p><strong>⚠️ Important:</strong> Your booking will NOT be confirmed until a staff member verifies your payment receipt.</p>

                    <p>Thank you for your patience!</p>

                    <p>May you be well and happy,<br>
                    <strong>Dhana Reservation System Team</strong></p>
                </div>
                <div class='footer'>
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>&copy; " . date('Y') . " Dhana Reservation System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $plainTextBody = "
Payment Receipt Received - Booking #{$bookingId}

Dear {$userName},

We have successfully received your payment receipt for your dhana booking.

BOOKING INFORMATION:
- Booking ID: #{$bookingId}
- Dhana Type: {$dhanaType}
- Date: {$bookingDate}
- Status: Pending Verification

WHAT HAPPENS NEXT?
- Our staff will verify your payment receipt
- You will receive a confirmation email once verified
- This usually takes 24-48 hours

IMPORTANT: Your booking will NOT be confirmed until a staff member verifies your payment receipt.

Thank you for your patience!

May you be well and happy,
Dhana Reservation System Team

---
This is an automated email. Please do not reply to this message.
        ";

        return $this->sendEmail($to, $subject, $htmlBody, $plainTextBody);
    }
}

// Helper function to get email service instance
function getEmailService() {
    return new EmailService();
}
?>
