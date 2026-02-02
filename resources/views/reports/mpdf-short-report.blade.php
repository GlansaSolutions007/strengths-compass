<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Axis Strengths Compass – Short Report</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.7;
            color: #1a202c;
            background: #ffffff;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        /* ---------------- COVER PAGE ---------------- */
        .cover-page {
            page-break-after: always;
            background: #667eea;
            background-image: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #667eea 100%);
            padding: 50px 40px;
            text-align: center;
            color: #fff;
        }

        .cover-logo img {
            max-width: 150px;
            margin-bottom: 50px;
            filter: brightness(0) invert(1);
        }

        .cover-title {
            font-size: 25pt;
            font-weight: 800;
            margin-bottom: 15px;
            letter-spacing: -0.5px;
        }

        .cover-subtitle {
            font-size: 15pt;
            margin-bottom: 50px;
            font-weight: 300;
            letter-spacing: 1px;
            opacity: 0.95;
        }

        .cover-info-box {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            margin: auto;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .cover-info-label {
            font-size: 10pt;
            margin-bottom: 6px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .cover-info-value {
            font-size: 16pt;
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: -0.3px;
        }

        .cover-info-value:last-child {
            margin-bottom: 0;
        }

        /* ---------------- SECTIONS ---------------- */
        .test-report-section {
            clear: both;
            margin-bottom: 20px;
            width: 100%;
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

        .test-report-section-title::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 50px;
            height: 3px;
            background: #764ba2;
        }

        /* ---------------- SUMMARY ---------------- */
        .test-report-summary {
            background: #fef5e7;
            background-image: linear-gradient(135deg, #fef5e7 0%, #fff5f5 100%);
            border-left: 4px solid #667eea;
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 9.5pt;
            line-height: 1.6;
            margin-bottom: 4px;
            color: #2d3748;
            /* Allow content to flow across pages */
        }

        .test-report-summary.sdb-guidance {
            border-left: 5px solid #f56565 !important;
            background: #fff5f5;
            background-image: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%);
            margin-top: 6px;
            margin-bottom: 0;
            padding: 10px 12px;
        }

        /* ---------------- CLUSTER GROUPS ---------------- */
        .cluster-group-section {
            margin-bottom: 8px;
            /* No page-break-inside - allow content to flow naturally */
        }

        .cluster-group-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .cluster-group-item {
            margin-bottom: 4px;
            padding: 8px 10px;
            background: #ffffff;
            background-image: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-left: 3px solid #667eea;
            border-radius: 8px;
            font-size: 9pt;
            line-height: 1.4;
            color: #2d3748;
        }

        .cluster-group-item:last-child {
            margin-bottom: 0;
        }

        .cluster-name-in-list {
            font-weight: 700;
            color: #667eea;
            margin-bottom: 4px;
            display: block;
            font-size: 9.5pt;
            letter-spacing: -0.2px;
        }

        .cluster-tendency-in-list {
            color: #4a5568;
            font-style: italic;
            font-size: 8.5pt;
            line-height: 1.4;
        }

        /* ---------------- SUMMARY PAGE ---------------- */
        .summary-page {
            padding-bottom: 0;
            margin-bottom: 0;
        }

        .summary-page .test-report-section-title {
            font-size: 16pt;
            margin-bottom: 8px;
            padding-bottom: 6px;
        }

        .test-report-item-label {
            font-size: 8.5pt;
            font-weight: 700;
            color: #667eea;
            margin: 10px 0 6px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
    </style>
</head>

<body>

    <!-- ================= COVER ================= -->
    <div class="cover-page">
        @if(!empty($logoBase64))
            <div class="cover-logo">
                <img src="data:image/png;base64,{{ $logoBase64 }}" style="max-width: 50%; height: auto;">
            </div>
        @endif

        <div class="cover-title">Axis Strengths Compass</div>
        <div class="cover-subtitle">Assessment Report</div>

        <div class="cover-info-box">
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

            <div class="cover-info-label">Email</div>
            <div class="cover-info-value">{{ $user->email ?? 'N/A' }}</div>

            <div class="cover-info-label">Test</div>
            <div class="cover-info-value">{{ $testName ?? 'Axis Strengths Assessment' }}</div>

            <div class="cover-info-label">Date</div>
            <div class="cover-info-value">{{ $generatedAt ?? now()->format('F d, Y') }}</div>
        </div>
    </div>

    <!-- Define named footer for Report Summary (disclaimer at bottom of each summary page) -->
    <htmlpagefooter name="disclaimer">
    <div style="font-size: 7pt; color: #6c757d; text-align: center; line-height: 1.2; padding: 8px 10px; border-top: 1px solid #e9ecef;">
    <p><b>Disclaimer:</b></p>
    <p>You have consented and taken this assessment for personal development purposes only. You understand results are not diagnostic, medical, or clinical, and represent self reported tendencies. These results may be influenced by context, mood, and self perception. Use them as a starting point for reflection and coaching, not as a definitive judgment. For mental health or medical concerns, consult a qualified professional. For any queries regarding the report, please send an email to: <b>guide@axiscompass.in</b></p>
    </div>
    </htmlpagefooter>

    <!-- Turn on disclaimer footer for Report Summary section (all summary pages) -->
    <sethtmlpagefooter name="disclaimer" page="ALL" value="1" />

    <!-- ================= SUMMARY ================= -->
    <div class="test-report-section summary-page">
        <div class="test-report-section-title"><b>Report Summary</b></div>
        <div class="test-report-summary">
            {!! $reportSummary ?? '' !!}
        </div>

        <!-- ================= CLUSTER GROUPS (Between Summary and Guidance) ================= -->
        @if(isset($clusterScores) && is_array($clusterScores) && count($clusterScores) > 0)
            @php
                // Filter clusters by category
                $strengthsClusters = array_filter($clusterScores, function ($clusterData) {
                    return isset($clusterData['category']) && strtolower($clusterData['category']) === 'high';
                });

                $emergingClusters = array_filter($clusterScores, function ($clusterData) {
                    return isset($clusterData['category']) && in_array(strtolower($clusterData['category']), ['medium', 'low']);
                });
            @endphp

            @if(count($strengthsClusters) > 0)
                <div class="test-report-section cluster-group-section" style="margin-top: 4px;">
                    <div class="test-report-item-label"
                        style="font-size: 11pt; margin-bottom: 3px; color: #1E37B3; font-weight: 800;">Strengths to Leverage
                    </div>
                    <ul class="cluster-group-list">
                        @foreach($strengthsClusters as $clusterName => $clusterData)
                            <li class="cluster-group-item">
                                <span class="cluster-name-in-list">{{ $clusterName }}</span>
                                @if(isset($clusterData['behaviour']) && !empty($clusterData['behaviour']))
                                    <div class="cluster-tendency-in-list">{{ $clusterData['behaviour'] }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(count($emergingClusters) > 0)
                <div class="test-report-section cluster-group-section" style="margin-top: 3px;">
                    <div class="test-report-item-label"
                        style="font-size: 11pt; margin-bottom: 3px; color: #1E37B3; font-weight: 800;">Emerging Capabilities &
                        Development Priorities</div>
                    <ul class="cluster-group-list">
                        @foreach($emergingClusters as $clusterName => $clusterData)
                            <li class="cluster-group-item">
                                <span class="cluster-name-in-list">{{ $clusterName }}</span>
                                @if(isset($clusterData['behaviour']) && !empty($clusterData['behaviour']))
                                    <div class="cluster-tendency-in-list">{{ $clusterData['behaviour'] }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

        @if(isset($sdbPercentage) && $sdbPercentage !== null && $sdbPercentage >= 90)
                <div class="test-report-summary sdb-guidance">
                    <p style="font-size: 9pt; color:rgb(243, 55, 55); font-weight: bold; margin-bottom: 4px;">
                        @php
                            $flagPath = public_path('assets/mark.png');
                            $flagBase64 = null;
                            if (file_exists($flagPath)) {
                                $flagData = file_get_contents($flagPath);
                                $flagBase64 = base64_encode($flagData);
                            }
                        @endphp
                        @if($flagBase64)
                            <img src="data:image/png;base64,{{ $flagBase64 }}" alt="Flag" style="display: inline-block; width: 24px; height: 20px; vertical-align: middle; margin-right: 2px;" />
                        @endif
                        <strong style="font-size: 10pt;">Guidance:</strong>
                    </p>
                    <p
                        style="font-size: 9pt; color: #4a5568; line-height: 1.5; font-style: italic; margin: 0; font-weight: bold;">
                        "This profile may benefit from further exploration to distinguish between current strengths and
                        aspirational qualities. A follow-up conversation with a coach can help personalize these insights."
                    </p>
                </div>
        @endif
    </div>

</body>

</html>