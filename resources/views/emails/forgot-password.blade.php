<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password - Strengths Compass</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 28px;
        }
        .content {
            margin-bottom: 30px;
        }
        .content p {
            margin-bottom: 15px;
            font-size: 16px;
        }
        .greeting {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .password-box {
            background-color: #f8f9fa;
            border: 2px dashed #3498db;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        .password-box .label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        .password-box .password {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            letter-spacing: 2px;
            font-family: 'Courier New', monospace;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #777;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #3498db;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            font-weight: bold;
        }
        .button:hover {
            background-color: #2980b9;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning p {
            margin: 0;
            color: #856404;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>Reset Your Password 🔐</h1>
        </div>
        
        <div class="content">
            <p class="greeting">Hello {{ $user->name ?? $user->first_name ?? 'there' }},</p>
            
            <p>We received a request to reset your password for your Strengths Compass account.</p>
            
            <p>Please use the following temporary password and click the link below to reset your password:</p>
            
            <div class="password-box">
                <div class="label">Your Temporary Password:</div>
                <div class="password">{{ $temporaryPassword }}</div>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $resetUrl }}" class="button">Reset Password</a>
            </div>
            
            <p>Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all; color: #3498db; font-size: 14px;">{{ $resetUrl }}</p>
            
            <div class="warning">
                <p><strong>⚠️ Important:</strong> This temporary password will expire in 60 minutes. Please reset your password as soon as possible.</p>
            </div>
            
            <p>If you did not request a password reset, please ignore this email or contact our support team if you have concerns.</p>
            
            <p>Best regards,<br>
            <strong>The Strengths Compass Team</strong></p>
        </div>
        
        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; {{ date('Y') }} Strengths Compass. All rights reserved.</p>
        </div>
    </div>
</body>
</html>

