<?php
/**
 * Additional Booking Email Methods
 * This file extends the EmailService class with booking-specific email methods
 */

require_once __DIR__ . '/email.php';

class BookingEmailService extends EmailService {
    
    /**
     * Send booking confirmed email (when staff confirms the booking)
     */
    public function sendBookingConfirmedEmail($bookingData) {
        $to = $bookingData['user_email'];
        $userName = $bookingData['user_name'];
        $bookingId = $bookingData['booking_id'];
        $dhanaType = $bookingData['dhana_type'];
        $bookingDate = date('F d, Y', strtotime($bookingData['booking_date']));
        $timeSlot = ucfirst(str_replace('_', ' ', $bookingData['time_slot']));
        $amount = number_format($bookingData['amount'], 2);
        
        $subject = "✅ Booking Confirmed - Booking #" . $bookingId;
        
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #4CAF50 0%, #388E3C 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
                .success-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }
                .booking-details { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e0e0e0; }
                .detail-label { font-weight: bold; color: #666; }
                .detail-value { color: #333; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 0.9rem; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Booking Confirmed!</h1>
                    <p>Your dhana reservation has been confirmed</p>
                </div>
                <div class='content'>
                    <p>Dear {$userName},</p>
                    
                    <div class='success-box'>
                        <strong>🎉 Great News!</strong><br>
                        Your payment has been verified and your dhana booking is now <strong>CONFIRMED</strong>!
                    </div>
                    
                    <div class='booking-details'>
                        <h3 style='margin-top: 0; color: #4CAF50;'>📋 Confirmed Booking Details</h3>
                        <div class='detail-row'>
                            <span class='detail-label'>Booking ID:</span>
                            <span class='detail-value'>#{$bookingId}</span>
                        </div>
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
                            <span class='detail-value' style='color: #28a745;'>✅ Confirmed</span>
                        </div>
                    </div>
                    
                    <p><strong>What to expect on the day:</strong></p>
                    <ul>
                        <li>Please arrive 15 minutes before your scheduled time</li>
                        <li>Bring this confirmation email or your booking ID</li>
                        <li>Contact us if you need any special arrangements</li>
                    </ul>
                    
                    <p>We look forward to welcoming you!</p>
                    
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
✅ Booking Confirmed - Booking #{$bookingId}

Dear {$userName},

GREAT NEWS!
Your payment has been verified and your dhana booking is now CONFIRMED!

CONFIRMED BOOKING DETAILS:
- Booking ID: #{$bookingId}
- Dhana Type: {$dhanaType}
- Date: {$bookingDate}
- Time Slot: {$timeSlot}
- Amount: LKR {$amount}
- Status: ✅ Confirmed

WHAT TO EXPECT ON THE DAY:
- Please arrive 15 minutes before your scheduled time
- Bring this confirmation email or your booking ID
- Contact us if you need any special arrangements

We look forward to welcoming you!

May you be well and happy,
Dhana Reservation System Team

---
This is an automated email. Please do not reply to this message.
        ";
        
        return $this->sendEmail($to, $subject, $htmlBody, $plainTextBody);
    }

    /**
     * Send booking cancelled email
     */
    public function sendBookingCancelledEmail($bookingData, $reason = 'auto') {
        $to = $bookingData['user_email'];
        $userName = $bookingData['user_name'];
        $bookingId = $bookingData['booking_id'];
        $dhanaType = $bookingData['dhana_type'];
        $bookingDate = date('F d, Y', strtotime($bookingData['booking_date']));

        $reasonText = '';
        if ($reason === 'auto') {
            $db = getDB();
            $timeoutSetting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'pending_booking_timeout_hours'");
            $timeoutHours = $timeoutSetting ? (int)$timeoutSetting['setting_value'] : 72;
            $reasonText = "Your booking was automatically cancelled because payment receipt was not uploaded within {$timeoutHours} hours.";
        } else {
            $reasonText = "Your booking has been cancelled by our staff.";
        }

        $subject = "❌ Booking Cancelled - Booking #" . $bookingId;

        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
                .warning-box { background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 20px 0; }
                .info-box { background: #e3f2fd; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; }
                .button { display: inline-block; background: #ff9800; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 0.9rem; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>❌ Booking Cancelled</h1>
                    <p>Booking #{$bookingId}</p>
                </div>
                <div class='content'>
                    <p>Dear {$userName},</p>

                    <div class='warning-box'>
                        <strong>Booking Cancelled</strong><br>
                        {$reasonText}
                    </div>

                    <div class='info-box'>
                        <strong>📋 Cancelled Booking Details:</strong><br>
                        <strong>Booking ID:</strong> #{$bookingId}<br>
                        <strong>Dhana Type:</strong> {$dhanaType}<br>
                        <strong>Date:</strong> {$bookingDate}<br>
                        <strong>Status:</strong> ❌ Cancelled
                    </div>

                    <p><strong>Would you like to make a new booking?</strong></p>
                    <p>If you still wish to make a dhana offering, you can create a new reservation at any time.</p>

                    <div style='text-align: center;'>
                        <a href='" . SITE_URL . "/booking-new.php' class='button'>📅 Make New Booking</a>
                    </div>

                    <p>If you have any questions, please contact us.</p>

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
❌ Booking Cancelled - Booking #{$bookingId}

Dear {$userName},

BOOKING CANCELLED
{$reasonText}

CANCELLED BOOKING DETAILS:
- Booking ID: #{$bookingId}
- Dhana Type: {$dhanaType}
- Date: {$bookingDate}
- Status: ❌ Cancelled

WOULD YOU LIKE TO MAKE A NEW BOOKING?
If you still wish to make a dhana offering, you can create a new reservation at any time.

Make a new booking here:
" . SITE_URL . "/booking-new.php

If you have any questions, please contact us.

May you be well and happy,
Dhana Reservation System Team

---
This is an automated email. Please do not reply to this message.
        ";

        return $this->sendEmail($to, $subject, $htmlBody, $plainTextBody);
    }

    /**
     * Send booking updated email (when staff modifies booking details)
     */
    public function sendBookingUpdatedEmail($bookingData, $changes) {
        $to = $bookingData['user_email'];
        $userName = $bookingData['user_name'];
        $bookingId = $bookingData['booking_id'];
        $dhanaType = $bookingData['dhana_type'];
        $bookingDate = date('F d, Y', strtotime($bookingData['booking_date']));
        $timeSlot = ucfirst(str_replace('_', ' ', $bookingData['time_slot']));
        $amount = number_format($bookingData['amount'], 2);
        $status = ucfirst($bookingData['status']);

        // Build changes list
        $changesHtml = '';
        $changesText = '';
        foreach ($changes as $field => $change) {
            $changesHtml .= "<li><strong>{$field}:</strong> {$change['old']} → {$change['new']}</li>";
            $changesText .= "- {$field}: {$change['old']} → {$change['new']}\n";
        }

        $subject = "📝 Booking Updated - Booking #" . $bookingId;

        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
                .info-box { background: #f3e5f5; border-left: 4px solid #9C27B0; padding: 15px; margin: 20px 0; }
                .booking-details { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e0e0e0; }
                .detail-label { font-weight: bold; color: #666; }
                .detail-value { color: #333; }
                .changes-list { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 0.9rem; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📝 Booking Updated</h1>
                    <p>Your booking details have been modified</p>
                </div>
                <div class='content'>
                    <p>Dear {$userName},</p>

                    <div class='info-box'>
                        <strong>📢 Important Update</strong><br>
                        Our staff has made changes to your dhana booking. Please review the updated details below.
                    </div>

                    <div class='changes-list'>
                        <strong>🔄 Changes Made:</strong>
                        <ul>
                            {$changesHtml}
                        </ul>
                    </div>

                    <div class='booking-details'>
                        <h3 style='margin-top: 0; color: #9C27B0;'>📋 Updated Booking Details</h3>
                        <div class='detail-row'>
                            <span class='detail-label'>Booking ID:</span>
                            <span class='detail-value'>#{$bookingId}</span>
                        </div>
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
                            <span class='detail-value'>{$status}</span>
                        </div>
                    </div>

                    <p>If you have any questions about these changes, please contact us.</p>

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
📝 Booking Updated - Booking #{$bookingId}

Dear {$userName},

IMPORTANT UPDATE
Our staff has made changes to your dhana booking. Please review the updated details below.

CHANGES MADE:
{$changesText}

UPDATED BOOKING DETAILS:
- Booking ID: #{$bookingId}
- Dhana Type: {$dhanaType}
- Date: {$bookingDate}
- Time Slot: {$timeSlot}
- Amount: LKR {$amount}
- Status: {$status}

If you have any questions about these changes, please contact us.

May you be well and happy,
Dhana Reservation System Team

---
This is an automated email. Please do not reply to this message.
        ";

        return $this->sendEmail($to, $subject, $htmlBody, $plainTextBody);
    }
}

// Helper function to get booking email service instance
function getBookingEmailService() {
    return new BookingEmailService();
}


