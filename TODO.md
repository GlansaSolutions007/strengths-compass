# Radar Chart PDF Display Fix

## Issue
The radar chart was not displaying properly in mPDF-generated PDFs because the `generateRadarmpdfChartSvg` method expected a 'percentage' key in the scores array, but the `enrichClusterScores` and `enrichConstructScores` methods were not calculating and adding this key.

## Solution
Added percentage calculation to both `enrichClusterScores` and `enrichConstructScores` methods using the existing `convertScoreToPercentage` method.

## Changes Made
- [x] Modified `enrichClusterScores` method to calculate and add 'percentage' key
- [x] Modified `enrichConstructScores` method to calculate and add 'percentage' key
- [x] Used existing `convertScoreToPercentage` method for consistency

## Testing
- The radar chart should now display correctly in mPDF-generated PDFs
- Both cluster and construct radar charts will show proper percentage values
- No breaking changes to existing functionality

## Files Modified
- `app/Http/Controllers/Api/ReportController.php`
  - `enrichClusterScores` method
  - `enrichConstructScores` method
