<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Completed - Strengths Compass</title>
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
            color: #27ae60;
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
        .test-info {
            background-color: #f8f9fa;
            border-left: 4px solid #27ae60;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .test-info h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 18px;
        }
        .test-info p {
            margin: 5px 0;
            color: #555;
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
            background-color: #27ae60;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            font-weight: bold;
        }
        .button:hover {
            background-color: #229954;
        }
        .success-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="success-icon">✅</div>
            <h1>Test Completed Successfully!</h1>
        </div>
        
        <div class="content">
            <p class="greeting">Hello {{ $user->name ?? $user->first_name ?? 'there' }},</p>
            
            <p>Congratulations! You have successfully completed the test. Thank you for taking the time to complete the assessment.</p>

            <p>Our team will review your results and get back to you soon.</p>
            
            <div class="test-info">
                <h3>Test Details</h3>
                <p><strong>Test:</strong> {{ $test->title }}</p>
                <p><strong>Completed on:</strong> {{ $testResult->created_at->format('F d, Y \a\t g:i A') }}</p>
                <!-- @if($testResult->average_score)
                <p><strong>Average Score:</strong> {{ number_format($testResult->average_score, 2) }}</p>
                @endif -->
            </div>
            
            <!-- <p>Your test results have been recorded and are now available in your account. You can view your detailed results and insights at any time.</p> -->
            
            <p>If you have any questions about your results or need assistance, please don't hesitate to contact our support team.</p>
            
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

