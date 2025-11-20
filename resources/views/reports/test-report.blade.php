<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Report - {{ $test->title ?? 'Strengths Compass' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            background: #fff;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .user-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .user-info-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }

        .user-info-row {
            display: table-row;
        }

        .user-info-cell {
            display: table-cell;
            width: 50%;
            padding-right: 15px;
            vertical-align: top;
        }

        .info-item {
            margin-bottom: 10px;
        }

        .info-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
        }

        .info-value {
            color: #333;
        }

        .scores-section {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
        }

        .score-item {
            display: table;
            width: 100%;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .score-item-inner {
            display: table-row;
        }

        .score-label,
        .score-value {
            display: table-cell;
        }

        .score-label {
            width: 70%;
        }

        .score-value {
            width: 30%;
            text-align: right;
        }

        .score-item:last-child {
            border-bottom: none;
        }

        .score-label {
            font-weight: 600;
            color: #555;
        }

        .score-value {
            font-weight: bold;
            color: #667eea;
            font-size: 14px;
        }

        .report-content {
            background: #fff;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            min-height: 200px;
        }

        .report-content p {
            margin-bottom: 15px;
            text-align: justify;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            color: #777;
            font-size: 10px;
        }

        .no-content {
            color: #999;
            font-style: italic;
            text-align: center;
            padding: 40px 20px;
        }

        @media print {
            .section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $test->title ?? 'Strengths Compass' }}</h1>
        <p>Test Report</p>
    </div>

    <div class="container">
        <!-- User Information Section -->
        <div class="section">
            <div class="section-title">User Information</div>
            <div class="user-info">
                <div class="user-info-grid">
                    <div class="user-info-row">
                        <div class="user-info-cell">
                            <div class="info-item">
                                <div class="info-label">Name</div>
                                <div class="info-value">{{ $user->name ?? ($user->first_name . ' ' . $user->last_name) }}</div>
                            </div>
                        </div>
                        <div class="user-info-cell">
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value">{{ $user->email }}</div>
                            </div>
                        </div>
                    </div>
                    @if(isset($user->profession) || isset($user->city))
                    <div class="user-info-row">
                        @if(isset($user->profession))
                        <div class="user-info-cell">
                            <div class="info-item">
                                <div class="info-label">Profession</div>
                                <div class="info-value">{{ $user->profession }}</div>
                            </div>
                        </div>
                        @endif
                        @if(isset($user->city))
                        <div class="user-info-cell">
                            <div class="info-item">
                                <div class="info-label">Location</div>
                                <div class="info-value">{{ $user->city }}, {{ $user->state }}, {{ $user->country }}</div>
                            </div>
                        </div>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Test Scores Section -->
        <div class="section">
            <div class="section-title">Test Scores</div>
            <div class="scores-section">
                <div class="score-item">
                    <div class="score-item-inner">
                        <span class="score-label">Total Score</span>
                        <span class="score-value">{{ number_format($totalScore ?? 0, 2) }}</span>
                    </div>
                </div>
                <div class="score-item">
                    <div class="score-item-inner">
                        <span class="score-label">Average Score</span>
                        <span class="score-value">{{ number_format($averageScore ?? 0, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cluster Scores Section -->
        @if(isset($clusterScores) && !empty($clusterScores))
        <div class="section">
            <div class="section-title">Cluster Scores</div>
            <div class="scores-section">
                @foreach($clusterScores as $cluster => $score)
                <div class="score-item">
                    <div class="score-item-inner">
                        <span class="score-label">{{ $cluster }}</span>
                        <span class="score-value">
                            @if(is_array($score))
                                @if(isset($score['average']))
                                    {{ number_format($score['average'], 2) }} ({{ $score['percentage'] ?? 'N/A' }}%)
                                @else
                                    {{ number_format($score['total'] ?? 0, 2) }}
                                @endif
                            @elseif(is_numeric($score))
                                {{ number_format($score, 2) }}
                            @else
                                {{ $score }}
                            @endif
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Report Summary Section -->
        <div class="section">
            <div class="section-title">Report Summary</div>
            <div class="report-content">
                @if(!empty($report->report_summary))
                {!! nl2br(e(is_array($report->report_summary) ? implode("\n", $report->report_summary) : $report->report_summary)) !!}

                @else
                    <div class="no-content">Report content will be available soon.</div>
                @endif
            </div>
        </div>

        <!-- Recommendations Section -->
        @if(!empty($report->recommendations))
        <div class="section">
            <div class="section-title">Recommendations</div>
            <div class="report-content">
                {!! nl2br(e($report->recommendations)) !!}
            </div>
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>Generated on {{ now()->format('F d, Y \a\t h:i A') }}</p>
            <p>Strengths Compass - Confidential Report</p>
        </div>
    </div>
</body>
</html>

