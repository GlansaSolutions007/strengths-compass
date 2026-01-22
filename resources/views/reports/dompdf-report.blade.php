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

@page {
    size: A4;
    margin: 15mm 15mm 25mm 15mm;

    @bottom-right {
        content: "Page " counter(page);
        font-size: 9pt;
        color: #999;
    }
}

body {
    font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
    font-size: 11pt;
    line-height: 1.6;
    color: #2c3e50;
}

/* ---------------- COVER PAGE ---------------- */
.cover-page {
    page-break-after: always;
    min-height: 260mm;
    background: rgb(93,110,193);
    padding: 40px;
    text-align: center;
    color: #fff;
}

.cover-logo img {
    max-width: 260px;
    margin-bottom: 40px;
    filter: brightness(0) invert(1);
}

.cover-title {
    font-size: 30pt;
    font-weight: bold;
    margin-bottom: 10px;
}

.cover-subtitle {
    font-size: 18pt;
    margin-bottom: 40px;
}

.cover-info-box {
    background: rgba(255,255,255,0.2);
    border-radius: 12px;
    padding: 30px;
    max-width: 480px;
    margin: auto;
}

.cover-info-label {
    font-size: 10pt;
    margin-bottom: 4px;
}

.cover-info-value {
    font-size: 15pt;
    font-weight: bold;
    margin-bottom: 15px;
}

/* ---------------- HEADER ---------------- */
.page-header {
    text-align: center;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
    margin-bottom: 25px;
}

.page-header img {
    max-width: 160px;
}

/* ---------------- SECTIONS ---------------- */
.test-report-section {
    clear: both;
    margin-bottom: 30px;
}

.test-report-section-title {
    font-size: 18pt;
    font-weight: bold;
    color: #667eea;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 3px solid #667eea;
}

/* ---------------- SUMMARY ---------------- */
.test-report-summary {
    background: #fff5f5;
    border-left: 4px solid #667eea;
    border-radius: 10px;
    padding: 18px;
    font-size: 10.5pt;
}

/* ---------------- CARDS ---------------- */
.test-report-cluster-item,
.test-report-construct-item {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #e9ecef;
    border-left: 4px solid #667eea;
    margin-bottom: 22px;
    page-break-inside: avoid;
}

.test-report-item-header {
    border-bottom: 2px solid #f0f0f0;
    margin-bottom: 12px;
    padding-bottom: 10px;
}

.test-report-item-title {
    font-size: 16pt;
    font-weight: bold;
}

.test-report-band {
    display: inline-block;
    margin-left: 10px;
    padding: 6px 18px;
    border-radius: 20px;
    font-size: 10pt;
    font-weight: 700;
}

.band-low {
    background: #fee2e2;
    color: #991b1b;
}

.band-medium {
    background: #fef3c7;
    color: #92400e;
}

.band-high {
    background: #d1fae5;
    color: #065f46;
}

.description-box {
    background: #f8f9fa;
    padding: 14px;
    border-radius: 8px;
    border-left: 3px solid #667eea;
}

.test-report-item-label {
    font-size: 11pt;
    font-weight: bold;
    color: #667eea;
    margin: 12px 0 6px;
}

.test-report-item-text {
    font-size: 10.5pt;
    text-align: justify;
}

/* ---------------- TENDENCY ---------------- */
.tendency-box {
    background: #eef2ff;
    border-left: 4px solid #667eea;
    border-radius: 8px;
    padding: 14px 18px;
    margin-top: 14px;
}

.tendency-label {
    font-size: 10pt;
    font-weight: bold;
    margin-bottom: 6px;
    text-transform: uppercase;
}

/* ---------------- PAGE BREAK ---------------- */
.page-break {
    page-break-before: always;
}

/* ---------------- RADAR ---------------- */
.radar-chart-page {
    page-break-before: always;
    text-align: center;
    padding-top: 40px;
}

.radar-chart-title {
    font-size: 24pt;
    margin-bottom: 30px;
}

/* ---------------- FOOTER ---------------- */
.test-report-footer {
    page-break-before: always;
    margin-top: 40px;
    font-size: 9pt;
    color: #6c757d;
    text-align: center;
    line-height: 1.4;
}
</style>
</head>

<body>

<!-- ================= COVER ================= -->
<div class="cover-page">
    @if(!empty($logoBase64))
        <div class="cover-logo">
            <img src="data:image/png;base64,{{ $logoBase64 }}">
        </div>
    @endif

    <div class="cover-title">Axis Strengths Compass</div>
    <div class="cover-subtitle">Assessment Report</div>

    <div class="cover-info-box">
        <div class="cover-info-label">Name</div>
        <div class="cover-info-value">{{ $user->name ?? 'N/A' }}</div>

        <div class="cover-info-label">Email</div>
        <div class="cover-info-value">{{ $user->email ?? 'N/A' }}</div>

        <div class="cover-info-label">Test</div>
        <div class="cover-info-value">{{ $testName ?? 'Strengths Assessment' }}</div>

        <div class="cover-info-label">Date</div>
        <div class="cover-info-value">{{ $generatedAt ?? now()->format('F d, Y') }}</div>
    </div>
</div>

<!-- ================= HEADER ================= -->
@if(!empty($logoBase64))
<div class="page-header">
    <img src="data:image/png;base64,{{ $logoBase64 }}">
</div>
@endif

<!-- ================= SUMMARY ================= -->
<div class="test-report-section">
    <div class="test-report-section-title">Report Summary</div>
    <div class="test-report-summary">
        This summary presents the cluster-level results from {{ $user->name ?? 'the candidate' }}’s
        Strengths Compass Assessment. Clusters are categorized into HIGH, MEDIUM, and LOW bands
        indicating strengths, emerging capabilities, and development priorities.
    </div>
</div>

<!-- ================= CLUSTERS ================= -->
<div class="page-break"></div>
<div class="test-report-section">
    <div class="test-report-section-title">Cluster Report Details</div>

    @foreach($clusterScores as $name => $data)
        <div class="test-report-cluster-item">
            <div class="test-report-item-header">
                <span class="test-report-item-title">{{ $name }}</span>
                <span class="test-report-band band-{{ strtolower($data['category']) }}">
                    {{ strtoupper($data['category']) }}
                </span>
            </div>

            <div class="test-report-item-label">Description</div>
            <div class="description-box">
                <div class="test-report-item-text">{{ $data['description'] }}</div>
            </div>

            <div class="tendency-box">
                <div class="tendency-label">Your Tendency</div>
                {{ $data['behaviour'] }}
            </div>
        </div>
    @endforeach
</div>

<!-- ================= CONSTRUCTS ================= -->
<div class="page-break"></div>
<div class="test-report-section">
    <div class="test-report-section-title">Construct Report Details</div>

    @foreach($constructScores as $name => $data)
        <div class="test-report-construct-item">
            <div class="test-report-item-header">
                <span class="test-report-item-title">{{ $name }}</span>
                <span class="test-report-band band-{{ strtolower($data['category']) }}">
                    {{ strtoupper($data['category']) }}
                </span>
            </div>

            <div class="test-report-item-label">Description</div>
            <div class="description-box">
                <div class="test-report-item-text">{{ $data['description'] }}</div>
            </div>

            <div class="tendency-box">
                <div class="tendency-label">Your Tendency</div>
                {{ $data['behaviour'] }}
            </div>
        </div>
    @endforeach
</div>

<!-- ================= RADARS ================= -->
@if(!empty($radarClusterChartBase64))
<div class="radar-chart-page">
    <div class="radar-chart-title">Cluster Radar Chart</div>
    {!! $radarClusterChartBase64 !!}
</div>
@endif

@if(!empty($radarConstructChartBase64))
<div class="radar-chart-page">
    <div class="radar-chart-title">Constructs Radar Chart</div>
    {!! $radarConstructChartBase64 !!}
</div>
@endif

<!-- ================= DISCLAIMER (ONCE) ================= -->
<div class="test-report-footer">
    You have consented and taken this assessment for personal development purposes only.
    Results are not diagnostic or clinical and reflect self-reported tendencies.
    For queries, contact <b>guide@axiscompass.in</b>
</div>

</body>
</html>
