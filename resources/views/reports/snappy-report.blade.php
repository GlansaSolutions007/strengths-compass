<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strengths Compass – Test Report</title>
    <style>
        /* ============================================
           RESET & BASE STYLES
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: A4;
            margin: 0;
        }

        body {
            font-family: 'Helvetica Neue', Arial, 'Segoe UI', sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #0a4f9785;
            background: #ffffff;
            padding-bottom: 60px;
        }

        /* ============================================
           FIXED FOOTER WITH LOGO (EVERY PAGE)
           ============================================ */
        .page-footer-logo {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 12px 0;
            background: #ffffff;
            border-top: 1px solid #e9ecef;
            text-align: center;
            z-index: 1000;
        }

        .page-footer-logo img {
            max-width: 150px;
            height: auto;
            display: inline-block;
        }

        /* Hide footer on cover page if needed */
        .cover-page + .page-footer-logo {
            display: none;
        }

        /* ============================================
           COVER PAGE (FIRST PAGE)
           ============================================ */
        .cover-page {
            page-break-after: always;
            width: 100%;
            min-height: 100vh;
            height: 100vh;
            background:rgb(93, 110, 193);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px 30px;
            position: relative;
        }

        .cover-logo-container {
            width: 100%;
            text-align: center;
            margin-bottom: 30px;
        }

        .cover-logo {
            max-width: 280px;
            height: auto;
            margin: 0 auto;
        }

        .cover-logo img {
            max-width: 100%;
            height: auto;
            filter: brightness(0) invert(1);
        }

        .cover-content {
            text-align: center;
            color: white;
            z-index: 1;
        }

        .cover-title {
            font-size: 30pt;
            font-weight: 700;
            margin-bottom: 15px;
            letter-spacing: 2px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .cover-subtitle {
            font-size: 18pt;
            font-weight: 300;
            margin-bottom: 40px;
            opacity: 0.95;
        }

        .cover-info-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 30px;
            margin-top: 30px;
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .cover-info-item {
            margin: 15px 0;
            text-align: left;
        }

        .cover-info-label {
            font-size: 10pt;
            opacity: 0.9;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .cover-info-value {
            font-size: 15pt;
            font-weight: 600;
            color: white;
        }

        /* ============================================
           PAGE HEADER WITH LOGO (ALL PAGES)
           ============================================ */
        .page-header {
            width: 100%;
            padding: 15px 40px;
            background: #ffffff;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            page-break-after: avoid;
            text-align: center;
        }

        .page-header-logo {
            max-width: 180px;
            height: auto;
            margin: 0 auto;
            text-align: center;
        }

        .page-header-logo img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        /* ============================================
           INNER PAGES - MODERN STYLING
           ============================================ */
        .content-page {
            padding: 0 40px 30px 40px;
            background: #ffffff;
        }

        .test-report-section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }

        .test-report-section-title {
            font-size: 18pt;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
            position: relative;
        }

        .section-page-start {
            page-break-before: always;
        }

        .test-report-section-title::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 60px;
            height: 3px;
            background: #764ba2;
        }

        /* ============================================
           USER INFORMATION - MODERN CARD
           ============================================ */
        .test-report-user-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            border: 1px solid #e9ecef;
        }

        .test-report-user-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .test-report-info-item {
            padding: 12px;
            background: white;
            border-radius: 6px;
            border-left: 3px solid #667eea;
        }

        .test-report-info-label {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 6px;
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .test-report-info-value {
            color: #2c3e50;
            font-size: 12pt;
            font-weight: 500;
        }

        /* ============================================
           REPORT SUMMARY - MODERN CARD
           ============================================ */
        .test-report-summary {
            background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%);
            border-left: 3px solid #667eea;
            border-radius: 10px;
            padding: 18px;
            min-height: 100px;
            font-size: 10.5pt;
            color: #333;
            line-height: 1.6;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }

        .test-report-summary.sdb-guidance {
            border-left: 3px solid rgb(234, 102, 102) !important;
        }

        /* ============================================
           SCORES SECTIONS - MODERN CARDS
           ============================================ */
        .test-report-scores-section {
            display: grid;
            gap: 15px;
        }

        .test-report-cluster-item,
        .test-report-construct-item {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 18px;
            border-radius: 10px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            page-break-inside: avoid;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .test-report-cluster-item::before,
        .test-report-construct-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 100%;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }

        .test-report-item-header {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 15px;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
            flex-wrap: wrap;
        }

        .test-report-item-title {
            font-size: 16pt;
            font-weight: 700;
            color: #2c3e50;
            display: inline-block;
        }

        .test-report-band {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            white-space: nowrap;
        }

        .band-low {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .band-medium {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .band-high {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .test-report-item-content {
            margin-top: 12px;
        }

        .test-report-item-label {
            font-size: 11pt;
            font-weight: 700;
            color: #667eea;
            margin-top: 12px;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
        }

        .test-report-item-label::before {
            content: '▸';
            margin-right: 6px;
            color: #764ba2;
            font-size: 12pt;
        }

        .test-report-item-text {
            font-size: 10.5pt;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 12px;
            text-align: justify;
        }

        /* ============================================
           TENDENCY SECTION - TRENDY MODERN DESIGN
           ============================================ */
        .tendency-box {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border-left: 3px solid #667eea;
            border-radius: 10px;
            padding: 15px 18px;
            margin-top: 12px;
            position: relative;
            box-shadow: 0 3px 8px rgba(102, 126, 234, 0.08);
        }

        .tendency-box::before {
            content: '💡';
            position: absolute;
            top: 15px;
            right: 18px;
            font-size: 20pt;
            opacity: 0.3;
        }

        .tendency-label {
            font-size: 10pt;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
        }

        .tendency-label::before {
            content: '';
            width: 25px;
            height: 2px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            margin-right: 10px;
            border-radius: 2px;
        }

        .tendency-text {
            font-size: 10.5pt;
            color: #2c3e50;
            line-height: 1.6;
            font-style: italic;
            position: relative;
            z-index: 1;
        }

        /* ============================================
           RADAR CHART PAGES
           ============================================ */
        .radar-chart-page {
            page-break-before: always;
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            padding: 0 40px 40px 40px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }
        
        .radar-chart-page:not(:last-of-type) {
            page-break-after: always;
        }
        

        .radar-chart-title {
            font-size: 24pt;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
            letter-spacing: 0.5px;
        }

        .radar-chart-container {
            /* width: 600px;
            height: 600px; */
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto;
            background: white;
            /* border-radius: 8px; */
            /* border: 1px solid #e0e0e0; */
            /* box-shadow: 0 4px 12px rgba(0,0,0,0.08); */
            /* padding: 30px; */
        }

        .radar-chart-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* ============================================
           FOOTER
           ============================================ */
        .test-report-footer {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 2px solid #e9ecef;
            text-align: center;
            color: #6c757d;
            font-size: 9pt;
        }

        /* ============================================
           DISCLAIMER PAGE (LAST PAGE)
           ============================================ */
        .disclaimer-page {
            page-break-before: always;
            padding: 60px 50px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: #ffffff;
        }

        .disclaimer-title {
            font-size: 20pt;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
        }

        .disclaimer-content {
            font-size: 10pt;
            line-height: 1.8;
            color: #4a5568;
            text-align: justify;
            max-width: 600px;
            margin: 0 auto;
        }

        .disclaimer-content p {
            margin-bottom: 15px;
        }

        .disclaimer-email {
            margin-top: 30px;
            text-align: center;
            font-size: 11pt;
            color: #667eea;
            font-weight: 600;
        }

        /* ============================================
           CLUSTER GROUPS SECTION
           ============================================ */
        .cluster-groups-intro {
            font-size: 11pt;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 25px;
            font-style: italic;
        }

        .cluster-group-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }

        .cluster-group-title {
            font-size: 16pt;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #667eea;
        }

        .cluster-group-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .cluster-group-item {
            margin-bottom: 12px;
            padding: 12px 15px;
            background: #f8f9fa;
            border-left: 3px solid #667eea;
            border-radius: 6px;
            font-size: 10.5pt;
            line-height: 1.7;
            color: #2c3e50;
        }

        .cluster-group-item:last-child {
            margin-bottom: 0;
        }

        .cluster-name-in-list {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 8px;
            display: block;
        }

        .cluster-tendency-in-list {
            color: #4a5568;
            font-style: italic;
        }

        /* ============================================
           UTILITY CLASSES
           ============================================ */
        .no-break {
            page-break-inside: avoid;
        }

        .page-break {
            page-break-before: always;
        }

        .description-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 8px;
            border-left: 3px solid #667eea;
        }
    </style>
</head>
<body>

    <!-- Fixed Footer Logo (Appears on Every Page) -->
    @if(isset($logoBase64) && !empty($logoBase64))
        <div class="page-footer-logo">
            <img src="data:image/png;base64,{{ $logoBase64 }}" alt="Strengths Compass Logo" />
        </div>
    @endif

    <!-- ============================================
         COVER PAGE (FIRST PAGE)
         ============================================ -->
    <div class="cover-page">
        <div class="cover-content">
            @if(isset($logoBase64) && !empty($logoBase64))
                <div class="cover-logo-container">
                    <div class="cover-logo">
                        <img src="data:image/png;base64,{{ $logoBase64 }}" alt="Strengths Compass Logo" />
                    </div>
                </div>
            @endif
            <h1 class="cover-title">Axis Strengths Compass</h1>
            <p class="cover-subtitle">Assessment Report</p>
            
            <div class="cover-info-box">
                <div class="cover-info-item">
                    <div class="cover-info-label">Email</div>
                    <div class="cover-info-value">{{ $user->email ?? 'N/A' }}</div>
                </div>
                <div class="cover-info-item">
                    <div class="cover-info-label">Name</div>
                    <div class="cover-info-value">
                        @if(isset($user->name) && !empty($user->name))
                            {{ $user->name }}
                        @elseif(isset($user->first_name) || isset($user->last_name))
                            {{ trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) }}
                        @else
                            N/A
                        @endif
                    </div>
                </div>
                <div class="cover-info-item">
                    <div class="cover-info-label">Test</div>
                    <div class="cover-info-value">{{ $testName ?? 'Strengths Assessment' }}</div>
                </div>
                <div class="cover-info-item">
                    <div class="cover-info-label">Date</div>
                    <div class="cover-info-value">{{ $generatedAt ?? now()->format('F d, Y') }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
         MAIN CONTENT (INNER PAGES)
         ============================================ -->
    <div class="content-page">
        <!-- Report Summary -->
        <div class="test-report-section">
            <div class="test-report-section-title">Report Summary</div>
            <div class="test-report-summary">
                {{ $reportSummary ?? '' }}
                
               
            </div>
        </div>

        <!-- Cluster Groups Introduction -->
   

        <!-- Cluster Groups: Strengths to Leverage & Emerging Capabilities -->
        @if(isset($clusterScores) && is_array($clusterScores) && count($clusterScores) > 0)
            @php
                // Define clusters for each group
                $strengthsToLeverage = [
                    'Character & Moral Foundation',
                    'Personal Agency & Growth',
                    'Openness & Future Orientation'
                ];
                
                $emergingCapabilities = [
                    'Caring & Self-Understanding',
                    'Drive & Achievement',
                    'Emotional Strength'
                ];
                
                // Filter clusters into groups
                $strengthsClusters = array_filter($clusterScores, function($clusterName) use ($strengthsToLeverage) {
                    return in_array($clusterName, $strengthsToLeverage);
                }, ARRAY_FILTER_USE_KEY);
                
                $emergingClusters = array_filter($clusterScores, function($clusterName) use ($emergingCapabilities) {
                    return in_array($clusterName, $emergingCapabilities);
                }, ARRAY_FILTER_USE_KEY);
            @endphp

            <!-- Strengths to Leverage -->
            @if(count($strengthsClusters) > 0)
                <div class="test-report-section cluster-group-section">
                    <!-- <div class="cluster-group-title">Strengths to Leverage:</div> -->
                    <ul class="cluster-group-list">
                        @foreach($strengthsToLeverage as $clusterName)
                            @if(isset($clusterScores[$clusterName]))
                                @php $clusterData = $clusterScores[$clusterName]; @endphp
                                <li class="cluster-group-item">
                                    <span class="cluster-name-in-list">{{ $clusterName }}</span>
                                    @if(isset($clusterData['behaviour']) && !empty($clusterData['behaviour']))
                                        <div class="cluster-tendency-in-list">{{ $clusterData['behaviour'] }}</div>
                                    @endif
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Emerging Capabilities & Development Priorities -->
            @if(count($emergingClusters) > 0)
                <div class="test-report-section cluster-group-section">
                    <!-- <div class="cluster-group-title">Emerging Capabilities & Development Priorities:</div> -->
                    <ul class="cluster-group-list">
                        @foreach($emergingCapabilities as $clusterName)
                            @if(isset($clusterScores[$clusterName]))
                                @php $clusterData = $clusterScores[$clusterName]; @endphp
                                <li class="cluster-group-item">
                                    <span class="cluster-name-in-list">{{ $clusterName }}</span>
                                    @if(isset($clusterData['behaviour']) && !empty($clusterData['behaviour']))
                                        <div class="cluster-tendency-in-list">{{ $clusterData['behaviour'] }}</div>
                                    @endif
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(isset($sdbPercentage) && $sdbPercentage !== null && $sdbPercentage >= 90)
                    <div class="test-report-summary sdb-guidance">
                        <p style="font-size: 11pt; color:rgb(243, 55, 55); font-weight: 600; margin-bottom: 8px;">
                            <img src="{{ asset('assets/—Pngtree—red flag sale_8699010.png') }}" alt="Flag" style="display: inline-block; width: 28px; height: 24px; vertical-align: middle; margin-right: 10px;" />
                            <strong style="font-size: 12pt;">Guidance:</strong>
                        </p>
                        <p style="font-size: 11pt; color: #4a5568; line-height: 1.6; font-style: italic; margin: 0; font-weight: 900;">
                        "This profile may benefit from further exploration to distinguish between current strengths and aspirational qualities. A follow-up conversation with a coach can help personalize these insights."
                        </p>
                    </div>
                @endif
        @endif

        <!-- Cluster Scores -->
        @if(isset($clusterScores) && is_array($clusterScores) && count($clusterScores) > 0)
            <div class="test-report-section section-page-start">
                <div class="test-report-section-title">Cluster Report Details</div>
                <div class="test-report-scores-section">
                    @foreach($clusterScores as $clusterName => $clusterData)
                        <div class="test-report-cluster-item no-break">
                            <div class="test-report-item-header">
                                <div class="test-report-item-title">{{ $clusterName }}</div>
                                @if(isset($clusterData['category']))
                                    <span class="test-report-band band-{{ strtolower($clusterData['category']) }}">
                                        {{ strtoupper($clusterData['category']) }}
                                    </span>
                                @endif
                            </div>
                            <div class="test-report-item-content">
                                @if(isset($clusterData['description']))
                                    <div class="test-report-item-label">Description</div>
                                    <div class="description-box">
                                        <div class="test-report-item-text">{{ $clusterData['description'] }}</div>
                                    </div>
                                @endif
                                @if(isset($clusterData['experience_stage_description']))
                                    <div class="test-report-item-label">Career Stage Insights</div>
                                    <div class="description-box">
                                        <div class="test-report-item-text">{{ $clusterData['experience_stage_description'] }}</div>
                                    </div>
                                @endif
                                @if(isset($clusterData['behaviour']))
                                    <div class="tendency-box">
                                        <div class="tendency-label">Your Tendency</div>
                                        <div class="tendency-text">{{ $clusterData['behaviour'] }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Construct Scores -->
        @if(isset($constructScores) && is_array($constructScores) && count($constructScores) > 0)
            <div class="test-report-section section-page-start">
                <div class="test-report-section-title">Construct Report Details</div>
                <div class="test-report-scores-section">
                    @foreach($constructScores as $constructName => $constructData)
                        <div class="test-report-construct-item no-break">
                            <div class="test-report-item-header">
                                <div class="test-report-item-title">{{ $constructName }}</div>
                                @if(isset($constructData['category']))
                                    <span class="test-report-band band-{{ strtolower($constructData['category']) }}">
                                        {{ strtoupper($constructData['category']) }}
                                    </span>
                                @endif
                            </div>
                            <div class="test-report-item-content">
                                @if(isset($constructData['description']))
                                    <div class="test-report-item-label">Description</div>
                                    <div class="description-box">
                                        <div class="test-report-item-text">{{ $constructData['description'] }}</div>
                                    </div>
                                @endif
                                @if(isset($constructData['behaviour']))
                                    <div class="tendency-box">
                                        <div class="tendency-label">Your Tendency</div>
                                        <div class="tendency-text">{{ $constructData['behaviour'] }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Footer -->
        <div class="test-report-footer">
            <p>Generated on {{ $generatedAt ?? now()->format('F d, Y') }}</p>
        </div>
    </div>

    <!-- ============================================
         CLUSTER RADAR CHART
         ============================================ -->
    @if(isset($radarClusterChartBase64) && !empty($radarClusterChartBase64))
        <div class="radar-chart-page">
            <h2 class="radar-chart-title">Cluster Radar Chart</h2>
            <div class="radar-chart-container">
                <!-- <img src="data:image/png;base64,{{ $radarClusterChartBase64 }}" alt="Cluster Radar Chart" class="radar-chart-image" /> -->
                 {!! $radarClusterChartBase64 !!}
            </div>
        </div>
    @endif

    <!-- ============================================
         CONSTRUCT RADAR CHART
         ============================================ -->
    @if(isset($radarConstructChartBase64) && !empty($radarConstructChartBase64))
        <div class="radar-chart-page">
            <h2 class="radar-chart-title">Constructs Radar Chart</h2>
            <div class="radar-chart-container">
                <!-- <img src="data:image/png;base64,{{ $radarConstructChartBase64 }}" alt="Construct Radar Chart" class="radar-chart-image" /> -->
                {!! $radarConstructChartBase64 !!}
            </div>
        </div>
    @endif

    <!-- ============================================
         DISCLAIMER PAGE (LAST PAGE)
         ============================================ -->
    <!-- <div class="disclaimer-page">
        <h2 class="disclaimer-title">Enquiry</h2>
        <div class="disclaimer-content">
            <div class="disclaimer-email">
                For any queries regarding the report, please send an email to: <strong>guide@axiscompass.in</strong>
            </div>
        </div>
    </div> -->

</body>
</html>
