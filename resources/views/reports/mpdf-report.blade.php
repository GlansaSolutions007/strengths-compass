<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Axis Strengths Compass – Report</title>

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
    /* min-height: 260mm; */
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
    background: rgba(255,255,255,0.2);
    border-radius: 20px;
    padding: 40px;
    max-width: 500px;
    margin: auto;
    border: 2px solid rgba(255,255,255,0.3);
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

/* ---------------- HEADER ---------------- */
.page-header {
    text-align: center;
    padding-bottom: 20px;
    border-bottom: 3px solid #e2e8f0;
    margin-bottom: 30px;
    background: #f7fafc;
    background-image: linear-gradient(to bottom, #f7fafc, #ffffff);
    padding-top: 20px;
}

.page-header img {
    max-width: 180px;
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
    line-height: 1.5;
    margin-bottom: 4px;
    color: #2d3748;
}

.test-report-summary.sdb-guidance {
    border-left: 5px solid #f56565 !important;
    background: #fff5f5;
    background-image: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%);
    margin-top: 6px;
    margin-bottom: 0;
    padding: 10px 12px;
}

/* ---------------- CARDS ---------------- */
.test-report-cluster-item,
.test-report-construct-item {
    background: #ffffff;
    padding: 18px 20px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #667eea;
    margin-bottom: 18px;
    page-break-inside: avoid;
    width: 100%;
    float: left;
    box-sizing: border-box;
    margin-right: 2%;
    vertical-align: top;
}

.test-report-cluster-item:nth-child(even),
.test-report-construct-item:nth-child(even) {
    margin-right: 0;
    float: right;
}

.cluster-item-wrapper {
    page-break-after: always;
}

.cluster-item-wrapper:last-child {
    page-break-after: auto;
}

.test-report-item-header {
    border-bottom: 2px solid #f1f5f9;
    margin-bottom: 10px;
    padding-bottom: 8px;
    display: block;
    width: 100%;
}

.test-report-item-title {
    font-size: 12pt;
    font-weight: 700;
    color: #1a202c;
    letter-spacing: -0.2px;
    display: block;
    margin-bottom: 6px;
}

.test-report-band {
   display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 9pt;
            font-weight: 900;
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

.description-box {
    background: #f8fafc;
    /* background-image: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); */
    /* padding: 12px 14px;
    border-radius: 8px; */
    /* border-left: 3px solid #667eea; */
}

.test-report-item-label {
    font-size: 8.5pt;
    font-weight: 700;
    color: #667eea;
    margin: 10px 0 6px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}

.test-report-item-text {
    font-size: 9pt;
    text-align: justify;
    line-height: 1.6;
    color: #4a5568;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

/* ---------------- TENDENCY ---------------- */
.tendency-box {
    background: #eef2ff;
    background-image: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
    border-left: 4px solid #667eea;
    border-radius: 8px;
    padding: 10px 12px;
    margin-top: 10px;
}

.tendency-label {
    font-size: 8pt;
    font-weight: 700;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #667eea;
}

.tendency-text {
    font-size: 9pt;
    color: #2d3748;
    line-height: 1.6;
    font-style: italic;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

/* ---------------- CLUSTER GROUPS ---------------- */
.cluster-group-section {
    margin-bottom: 4px;
    page-break-inside: avoid;
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

/* ---------------- PAGE BREAK ---------------- */
.page-break {
    page-break-before: always;
    clear: both;
}

/* ---------------- RADAR ---------------- */
.radar-chart-page {
    page-break-before: always;
    text-align: center;
    padding: 40px 20px;
    /* min-height: 200mm; */
    background: #f7fafc;
    background-image: linear-gradient(135deg, #f7fafc 0%, #ffffff 100%);
}

.radar-chart-title {
    font-size: 22pt;
    font-weight: 800;
    margin-bottom: 30px;
    color: #1a202c;
    letter-spacing: -0.5px;
    position: relative;
    display: inline-block;
    padding-bottom: 12px;
}

.radar-chart-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    margin-left: -40px;
    width: 80px;
    height: 4px;
    background: #667eea;
    border-radius: 2px;
}

.radar-chart-container {
    text-align: center;
    margin: 0 auto;
    max-width: 100%;
    background: #ffffff;
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.radar-chart-container svg {
    max-width: 100%;
    width: 100%;
    height: auto;
    display: block;
    margin: 0 auto;
    max-height: 600px;
}

.radar-chart-container img {
    max-width: 100%;
    height: auto;
    display: block;
    margin: 0 auto;
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

/* ---------------- TWO COLUMN LAYOUT FOR CLUSTERS/CONSTRUCTS ---------------- */
/* .cluster-construct-wrapper {
    width: 100%;
    page-break-inside: avoid;
} */

.cluster-construct-wrapper {
    width: 100%;
    clear: both;
    overflow: hidden;
}

.cluster-construct-wrapper::after {
    content: "";
    display: table;
    clear: both;
}

/* ---------------- FOOTER ---------------- */
.test-report-footer {
    page-break-before: always;
    margin-top: 50px;
    font-size: 9pt;
    color: #718096;
    text-align: center;
    line-height: 1.6;
    padding: 30px 20px;
    border-top: 3px solid #e2e8f0;
    background: #f7fafc;
    background-image: linear-gradient(135deg, #f7fafc 0%, #ffffff 100%);
}


/* ===== TWO PER PAGE LAYOUT ===== */



.test-report-construct-item {
    float: left;
    margin-right: 4%;
    margin-bottom: 18px;
    page-break-inside: avoid;
}

.test-report-construct-item:nth-child(2n) {
    margin-right: 0;
}

.clearfix {
    clear: both;
    height: 0;
    line-height: 0;
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
        <div class="cover-info-value">{{ $testName ?? 'Strengths Assessment' }}</div>

        <div class="cover-info-label">Date</div>
        <div class="cover-info-value">{{ $generatedAt ?? now()->format('F d, Y') }}</div>
    </div>
</div>

<!-- ================= SUMMARY ================= -->
<div class="test-report-section summary-page">
    <div class="test-report-section-title"><b>Report Summary</b></div>
    <div class="test-report-summary">
        {{ $reportSummary ?? '' }}
    </div>
    
    <!-- ================= CLUSTER GROUPS (Between Summary and Guidance) ================= -->
    @if(isset($clusterScores) && is_array($clusterScores) && count($clusterScores) > 0)
        @php
            // Filter clusters by category
            $strengthsClusters = array_filter($clusterScores, function($clusterData) {
                return isset($clusterData['category']) && strtolower($clusterData['category']) === 'high';
            });
            
            $emergingClusters = array_filter($clusterScores, function($clusterData) {
                return isset($clusterData['category']) && in_array(strtolower($clusterData['category']), ['medium', 'low']);
            });
        @endphp

        @if(count($strengthsClusters) > 0)
            <div class="test-report-section cluster-group-section" style="margin-top: 4px;">
                <div class="test-report-item-label" style="font-size: 11pt; margin-bottom: 3px; color: #1E37B3; font-weight: 800;">Strengths to Leverage</div>
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
                <div class="test-report-item-label" style="font-size: 11pt; margin-bottom: 3px; color: #1E37B3; font-weight: 800;">Emerging Capabilities & Development Priorities</div>
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
            <p style="font-size: 9pt; color:rgb(243, 55, 55); font-weight: bold; margin-bottom: 4px;">Guidance:</p>
            <p style="font-size: 9pt; color: #4a5568; line-height: 1.5; font-style: italic; margin: 0; font-weight: bold;">
            "This profile may benefit from further exploration to distinguish between current strengths and aspirational qualities. A follow-up conversation with a coach can help personalize these insights."
            </p>
        </div>
    @endif
</div>

<!-- ================= FULL REPORT STARTS HERE ================= -->
<div class="page-break"></div>

<!-- ================= CLUSTERS ================= -->
@if(isset($clusterScores) && is_array($clusterScores) && count($clusterScores) > 0)
<!-- <div class="page-break"></div> -->
<div class="test-report-section">
    <div class="test-report-section-title"><b>Cluster Report Details</b></div>

    @php
        $clusterIndex = 0;
        $clustersArray = [];
        foreach($clusterScores as $name => $data) {
            $clustersArray[] = ['name' => $name, 'data' => $data];
        }
    @endphp

    <div style="width: 100%; overflow: hidden; clear: both; margin: 0; padding: 0;">
    @foreach($clustersArray as $index => $item)
        @php
            $name = $item['name'];
            $data = $item['data'];
            $clusterIndex++;
            $pageBreakStyle = ($clusterIndex > 2 && $clusterIndex % 2 == 1) ? 'page-break-before: always;' : '';
        @endphp
        
        <div class="test-report-cluster-item" style="{{ $pageBreakStyle }}">
            <div class="test-report-item-header">
                <span class="test-report-item-title">{{ $name }}</span>
                @if(isset($data['category']))
                    <span class="test-report-band band-{{ strtolower($data['category']) }}">
                        {{ strtoupper($data['category']) }}
                    </span>
                @endif
            </div>

            @if(isset($data['description']))
                <div class="test-report-item-label">Description</div>
                <div class="description-box">
                    <div class="test-report-item-text">{{ $data['description'] }}</div>
                </div>
            @endif

            @if(isset($data['behaviour']))
                <div class="tendency-box">
                    <div class="tendency-label">Your Tendency</div>
                    <div class="tendency-text">{{ $data['behaviour'] }}</div>
                </div>
            @endif
        </div>
        
        @if($clusterIndex % 2 == 0)
            <div style="clear: both; height: 0;"></div>
        @endif
    @endforeach
    </div>
</div>
@endif

<!-- ================= CONSTRUCTS ================= -->
@if(isset($constructScores) && is_array($constructScores) && count($constructScores) > 0)
<!-- <div class="test-report-section" style="page-break-before: always;">
    <div class="test-report-section-title">Construct Report Details</div>

    @php
        $constructIndex = 0;
        $constructsArray = [];
        foreach($constructScores as $name => $data) {
            $constructsArray[] = ['name' => $name, 'data' => $data];
        }
    @endphp

    <div style="width: 100%; overflow: hidden; clear: both; margin: 0; padding: 0;">
    @foreach($constructsArray as $index => $item)
        @php
            $name = $item['name'];
            $data = $item['data'];
            $constructIndex++;
            $pageBreakStyle = ($constructIndex > 2 && $constructIndex % 2 == 1) ? 'page-break-before: always;' : '';
        @endphp
        
        <div class="test-report-construct-item" style="{{ $pageBreakStyle }}">
            <div class="test-report-item-header">
                <span class="test-report-item-title">{{ $name }}</span>
                @if(isset($data['category']))
                    <span class="test-report-band band-{{ strtolower($data['category']) }}">
                        {{ strtoupper($data['category']) }}
                    </span>
                @endif
            </div>

            @if(isset($data['description']))
                <div class="test-report-item-label">Description</div>
                <div class="description-box">
                    <div class="test-report-item-text">{{ $data['description'] }}</div>
                </div>
            @endif

            @if(isset($data['behaviour']))
                <div class="tendency-box">
                    <div class="tendency-label">Your Tendency</div>
                    <div class="tendency-text">{{ $data['behaviour'] }}</div>
                </div>
            @endif
        </div>
        
        @if($constructIndex % 2 == 0)
            <div style="clear: both; height: 0;"></div>
        @endif
    @endforeach
    </div>
</div> -->
@endif


{{-- ================= CONSTRUCTS ================= --}}
@if(isset($constructScores) && is_array($constructScores) && count($constructScores) > 0)
<div class="clearfix"></div>
<div class="page-break"></div>
<div class="test-report-section">
    <div class="test-report-section-title"><b>Construct Report Details</b></div>

    @php
        $constructsArray = [];
        foreach($constructScores as $name => $data) {
            $constructsArray[] = [
                'name' => $name,
                'data' => $data
            ];
        }

        // Split constructs into pages of 2
        $constructChunks = array_chunk($constructsArray, 2);
    @endphp

    @foreach($constructChunks as $pageIndex => $chunk)

        {{-- Page break BEFORE every new page except first --}}
        @if($pageIndex > 0)
            <div class="page-break"></div>
        @endif

        <div class="cluster-construct-wrapper">

            @foreach($chunk as $item)
                @php
                    $name = $item['name'];
                    $data = $item['data'];
                @endphp

                <div class="test-report-construct-item">
                    <div class="test-report-item-header">
                        <span class="test-report-item-title">{{ $name }}</span>

                        @if(isset($data['category']))
                            <span class="test-report-band band-{{ strtolower($data['category']) }}">
                                {{ strtoupper($data['category']) }}
                            </span>
                        @endif
                    </div>

                    @if(isset($data['description']))
                        <div class="test-report-item-label">Description</div>
                        <div class="description-box">
                            <div class="test-report-item-text">
                                {{ $data['description'] }}
                            </div>
                        </div>
                    @endif

                    @if(isset($data['behaviour']))
                        <div class="tendency-box">
                            <div class="tendency-label">Your Tendency</div>
                            <div class="tendency-text">
                                {{ $data['behaviour'] }}
                            </div>
                        </div>
                    @endif
                </div>

            @endforeach

        </div>

    @endforeach
</div>

@endif


<!-- ================= RADARS ================= -->
@if(!empty($radarClusterChartBase64))
<div class="radar-chart-page">
    <div class="radar-chart-title">Cluster Radar Chart</div>
    <div class="radar-chart-container">
       {!! $radarClusterChartBase64 !!}
    </div>
</div>
@endif

@if(!empty($radarConstructChartBase64))
<div class="radar-chart-page">
    <div class="radar-chart-title">Constructs Radar Chart</div>
    <div class="radar-chart-container">
       {!! $radarConstructChartBase64 !!}
    </div>
</div>
@endif


</body>
</html>
