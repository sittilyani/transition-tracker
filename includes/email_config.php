<?php
// Email configuration for PHPMailer using Composer autoloader

// Load Composer's autoloader (adjust path as needed)
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendWelcomeEmail($to_email, $to_name, $username, $password) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->SMTPDebug = 0;                      // Disable debug output (set to 2 for testing)
        $mail->isSMTP();                            // Send using SMTP
        $mail->Host       = 'mail.the-touch-haven-investments.store'; // Set the SMTP server
        $mail->SMTPAuth   = true;                   // Enable SMTP authentication
        $mail->Username   = 'admin@the-touch-haven-investments.store'; // SMTP username
        $mail->Password   = 'Pharmacy@123'; // SMTP password - UPDATE THIS
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable SSL encryption
        $mail->Port       = 465;                     // TCP port to connect to

        // Alternative settings (try these if SSL doesn't work)
        // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        // $mail->Port       = 587;

        // Recipients
        $mail->setFrom('admin@the-touch-haven-investments.store', 'Training Management System');
        $mail->addAddress($to_email, $to_name);     // Add a recipient
        $mail->addReplyTo('admin@the-touch-haven-investments.store', 'Information');

        // Content
        $mail->isHTML(true);                         // Set email format to HTML
        $mail->Subject = 'Your Training Management System Login Credentials';

        // HTML Email Body
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; background-color: #f4f7fc; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
                .header { background: #0D1A63; color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .credentials { background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #0D1A63; }
                .credentials p { margin: 10px 0; }
                .credentials strong { color: #0D1A63; width: 100px; display: inline-block; }
                .button { display: inline-block; background: #0D1A63; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; margin-top: 20px; font-weight: bold; }
                .button:hover { background: #1a2a7a; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #e0e0e0; }
                .warning { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 8px; margin: 20px 0; }
                .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 10px; border-radius: 5px; font-size: 13px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to Training Management System</h1>
                </div>
                <div class='content'>
                    <p>Dear <strong>$to_name</strong>,</p>
                    <p>Your account has been created successfully in the Training Management System. Below are your login credentials:</p>

                    <div class='credentials'>
                        <p><strong>Username:</strong> <span style='font-family: monospace; font-size: 16px;'>$username</span></p>
                        <p><strong>Password:</strong> <span style='font-family: monospace; font-size: 16px; background: #fff; padding: 5px 10px; border-radius: 5px;'>$password</span></p>
                    </div>

                    <div class='info'>
                        <strong>?? Important Security Notice:</strong>
                        <p style='margin: 10px 0 0 0;'>For security reasons, please change your password immediately after your first login.</p>
                    </div>

                    <p style='text-align: center;'>
                        <a href='https://the-touch-haven-investments.store/login.php' class='button'>Login to System</a>
                    </p>

                    <div class='warning'>
                        <strong>?? Please Note:</strong>
                        <ul style='margin: 10px 0 0 20px; padding-left: 0;'>
                            <li>This is your system-generated password</li>
                            <li>Change your password immediately after first login</li>
                            <li>Never share your password with anyone</li>
                            <li>The system will prompt you to change password on first login</li>
                            <li>If you didn't request this account, please contact your administrator immediately</li>
                        </ul>
                    </div>
                </div>
                <div class='footer'>
                    <p>This is an automated message from the Training Management System. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " Training Management System. All rights reserved.</p>
                    <p style='margin-top: 10px; font-size: 11px;'>the-touch-haven-investments.store</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // Plain text version for non-HTML email clients
        $mail->AltBody = "Hello $to_name,\n\nYour account has been created successfully.\n\nLOGIN CREDENTIALS:\nUsername: $username\nPassword: $password\n\nLOGIN URL: https://the-touch-haven-investments.store/login.php\n\nIMPORTANT: Please change your password immediately after first login for security reasons.\n\nThis is an automated message. Please do not reply.";

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];

    } catch (Exception $e) {
        // Log the error for debugging
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return ['success' => false, 'message' => "Email could not be sent. Error: " . $mail->ErrorInfo];
    }
}

// Function to generate random password
function generateRandomPassword($length = 12) {
    // Define character sets
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%&*_+-=';

    // Ensure at least one character from each set
    $password = '';
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];

    // Fill the rest with random characters from all sets
    $all_characters = $uppercase . $lowercase . $numbers . $special;
    $remaining_length = $length - 4;

    for ($i = 0; $i < $remaining_length; $i++) {
        $password .= $all_characters[random_int(0, strlen($all_characters) - 1)];
    }

    // Shuffle the password to mix the guaranteed characters
    return str_shuffle($password);
}

// Function to test email configuration
function testEmailConfiguration($test_email = null) {
    if (!$test_email) {
        // Use a default test email or get from session
        $test_email = $_SESSION['email'] ?? 'admin@the-touch-haven-investments.store';
    }

    return sendWelcomeEmail(
        $test_email,
        'Test User',
        'test.user',
        'Test@123456'
    );
}
?>