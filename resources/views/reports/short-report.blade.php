<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Report - Strengths Compass</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #333;
            background: #fff;
        }

        .header {
            background-color: #667eea;
            color: white;
            padding: 15px;
            text-align: center;
            margin-bottom: 15px;
            width: 100%;
            display: block;
        }

        .header h1 {
            font-size: 22px;
            margin-bottom: 4px;
            color: white;
            font-weight: bold;
        }

        .header p {
            font-size: 11px;
            color: white;
            opacity: 0.9;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            padding: 10px 15px;
        }

        .section {
            margin-bottom: 12px;
        }
        
        @media print {
            .section {
                page-break-inside: auto;
            }
        }

        .main-heading {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }

        .main-heading h2 {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
            margin: 0;
        }

        .section-title {
            font-size: 15px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #667eea;
        }

        .user-info {
            background: #f8f9fa;
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 10px;
        }

        .user-info-item {
            margin-bottom: 5px;
        }

        .user-info-label {
            font-weight: bold;
            color: #555;
            display: inline-block;
            width: 80px;
        }

        .cluster-section {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }

        .cluster-header {
            font-size: 13px;
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .band-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .band-high {
            background-color: #d1fae5;
            color: #065f46;
        }

        .band-medium {
            background-color: #fef3c7;
            color: #92400e;
        }

        .band-low {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .construct-item {
            margin-left: 12px;
            margin-bottom: 6px;
            padding-left: 8px;
            border-left: 2px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .construct-name {
            font-weight: 600;
            color: #374151;
            font-size: 10px;
        }

        .footer {
            margin-top: 20px;
            padding-top: 12px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #777;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Strengths Compass</h1>
        <p>Test Report - Strengths Compass Assessment</p>
    </div>

    <div class="container">

        <!-- User Information Section -->
        <div class="section">
            <div class="section-title">User Information</div>
            <div class="user-info">
                <div class="user-info-item">
                    <span class="user-info-label">Name:</span>
                    <span>{{ $user->name ?? ($user->first_name . ' ' . $user->last_name) }}</span>
                </div>
                <div class="user-info-item">
                    <span class="user-info-label">Email:</span>
                    <span>{{ $user->email }}</span>
                </div>
            </div>
        </div>

        <!-- Cluster Scores Section -->
        @if(isset($clusterInsights) && !empty($clusterInsights))
        <div class="section">
            <div class="section-title">Cluster Scores</div>
            @foreach($clusterInsights as $cluster)
            <div class="cluster-section">
                <div class="cluster-header">
                    <span>{{ $cluster['name'] }}</span>
                    <span class="band-badge band-{{ strtolower($cluster['strength_band']) }}">
                        {{ $cluster['strength_band'] }}
                    </span>
                </div>

                @if(isset($constructDetails) && !empty($constructDetails))
                    @php
                        $clusterConstructs = array_filter($constructDetails, function($construct) use ($cluster) {
                            return isset($construct['cluster_name']) && $construct['cluster_name'] === $cluster['name'];
                        });
                    @endphp
                    @if(!empty($clusterConstructs))
                        @foreach($clusterConstructs as $construct)
                        <div class="construct-item">
                            <span class="construct-name">{{ $construct['name'] }}</span>
                            @if(isset($construct['band']))
                                <span class="band-badge band-{{ strtolower($construct['band']) }}">
                                    {{ $construct['band'] }}
                                </span>
                            @endif
                        </div>
                        @endforeach
                    @endif
                @endif
            </div>
            @endforeach
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
