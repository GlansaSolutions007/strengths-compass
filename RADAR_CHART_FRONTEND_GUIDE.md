# Radar Chart Frontend Implementation Guide

## 📡 API Endpoints

The radar chart data is automatically included in these endpoints:

### 1. Get Single Test Result
```
GET /api/test-results/{testResultId}
```
**Response includes:**
```json
{
  "status": true,
  "data": {
    "test_result_id": 1,
    "scores": { ... },
    "radar_chart": {
      "labels": ["Cluster 1", "Cluster 2", "Cluster 3"],
      "datasets": [{
        "label": "Cluster Scores",
        "data": [4.2, 3.8, 4.5],
        "backgroundColor": "rgba(54, 162, 235, 0.2)",
        "borderColor": "rgba(54, 162, 235, 1)",
        "borderWidth": 2
      }],
      "maxValue": 5
    }
  }
}
```

### 2. Submit Test (includes radar chart immediately)
```
POST /api/tests/{testId}/submit
```

### 3. Get All Results for a User
```
GET /api/users/{userId}/test-results
```

### 4. Get All Results for a Test
```
GET /api/tests/{testId}/results
```

---

## 🎨 Frontend Implementation Examples

### Option 1: Chart.js (Recommended)

#### Installation
```bash
npm install chart.js
# or
yarn add chart.js
```

#### React Example
```jsx
import React, { useEffect, useRef, useState } from 'react';
import {
  Chart as ChartJS,
  RadialLinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
  Legend
} from 'chart.js';
import { Radar } from 'react-chartjs-2';

// Register Chart.js components
ChartJS.register(
  RadialLinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
  Legend
);

function TestResultRadarChart({ testResultId }) {
  const [chartData, setChartData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchRadarChartData();
  }, [testResultId]);

  const fetchRadarChartData = async () => {
    try {
      const response = await fetch(
        `http://your-api-url/api/test-results/${testResultId}`
      );
      const result = await response.json();
      
      if (result.status && result.data.radar_chart) {
        setChartData(result.data.radar_chart);
      }
    } catch (error) {
      console.error('Error fetching radar chart data:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <div>Loading chart...</div>;
  if (!chartData || !chartData.labels.length) {
    return <div>No data available</div>;
  }

  const options = {
    responsive: true,
    maintainAspectRatio: true,
    scales: {
      r: {
        beginAtZero: true,
        max: chartData.maxValue || 5,
        ticks: {
          stepSize: 1
        }
      }
    },
    plugins: {
      legend: {
        display: true,
        position: 'top'
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            return `${context.dataset.label}: ${context.parsed.r}`;
          }
        }
      }
    }
  };

  return (
    <div style={{ width: '500px', height: '500px', margin: '0 auto' }}>
      <Radar data={chartData} options={options} />
    </div>
  );
}

export default TestResultRadarChart;
```

### Option 2: Recharts (React Only)

#### Installation
```bash
npm install recharts
```

#### React Component
```jsx
import React, { useEffect, useState } from 'react';
import {
  Radar,
  RadarChart,
  PolarGrid,
  PolarAngleAxis,
  PolarRadiusAxis,
  ResponsiveContainer,
  Legend
} from 'recharts';

function TestResultRadarChart({ testResultId }) {
  const [chartData, setChartData] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchRadarChartData();
  }, [testResultId]);

  const fetchRadarChartData = async () => {
    try {
      const response = await fetch(
        `http://your-api-url/api/test-results/${testResultId}`
      );
      const result = await response.json();
      
      if (result.status && result.data.radar_chart) {
        const radarData = result.data.radar_chart;
        // Transform data for Recharts format
        const formattedData = radarData.labels.map((label, index) => ({
          cluster: label,
          score: radarData.datasets[0].data[index]
        }));
        setChartData(formattedData);
      }
    } catch (error) {
      console.error('Error fetching radar chart data:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <div>Loading chart...</div>;
  if (!chartData.length) return <div>No data available</div>;

  return (
    <ResponsiveContainer width="100%" height={500}>
      <RadarChart data={chartData}>
        <PolarGrid />
        <PolarAngleAxis dataKey="cluster" />
        <PolarRadiusAxis angle={90} domain={[0, 5]} />
        <Radar
          name="Cluster Scores"
          dataKey="score"
          stroke="#3b82f6"
          fill="#3b82f6"
          fillOpacity={0.6}
        />
        <Legend />
      </RadarChart>
    </ResponsiveContainer>
  );
}

export default TestResultRadarChart;
```

---

### Option 3: After Test Submission (Immediate Display)

If you want to show the radar chart immediately after test submission:

```javascript
// After submitting test answers
async function submitTest(testId, answers) {
  try {
    const response = await fetch(
      `http://your-api-url/api/tests/${testId}/submit`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          user_id: userId,
          answers: answers
        })
      }
    );

    const result = await response.json();
    
    if (result.status) {
      // Radar chart data is already in the response!
      const radarChartData = result.data.radar_chart;
      
      // Render chart immediately
      renderRadarChart(radarChartData);
    }
  } catch (error) {
    console.error('Error submitting test:', error);
  }
}
```

---

## 🎯 Complete React Example with Error Handling

```jsx
import React, { useEffect, useState } from 'react';
import { Radar } from 'react-chartjs-2';
import { Chart as ChartJS, RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend } from 'chart.js';

ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend);

const API_BASE_URL = 'http://your-api-url/api';

function TestResultView({ testResultId }) {
  const [testResult, setTestResult] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetchTestResult();
  }, [testResultId]);

  const fetchTestResult = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch(`${API_BASE_URL}/test-results/${testResultId}`);
      const data = await response.json();
      
      if (!response.ok || !data.status) {
        throw new Error(data.message || 'Failed to fetch test result');
      }
      
      setTestResult(data.data);
    } catch (err) {
      setError(err.message);
      console.error('Error:', err);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <div className="loading">Loading test results...</div>;
  }

  if (error) {
    return <div className="error">Error: {error}</div>;
  }

  if (!testResult) {
    return <div>No test result found</div>;
  }

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: true,
    scales: {
      r: {
        beginAtZero: true,
        max: testResult.radar_chart?.maxValue || 5,
        ticks: {
          stepSize: 1
        }
      }
    },
    plugins: {
      legend: {
        display: true,
        position: 'top'
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            return `${context.dataset.label}: ${context.parsed.r.toFixed(2)}`;
          }
        }
      }
    }
  };

  return (
    <div className="test-result-container">
      <h2>Test Result #{testResult.test_result_id}</h2>
      
      <div className="scores-summary">
        <p>Total Score: {testResult.scores.total_score}</p>
        <p>Average Score: {testResult.scores.average_score}</p>
      </div>

      {testResult.radar_chart && testResult.radar_chart.labels.length > 0 ? (
        <div className="radar-chart-wrapper">
          <h3>Cluster Scores Radar Chart</h3>
          <div style={{ width: '500px', height: '500px', margin: '0 auto' }}>
            <Radar 
              data={testResult.radar_chart} 
              options={chartOptions} 
            />
          </div>
        </div>
      ) : (
        <div>No radar chart data available</div>
      )}

      <div className="cluster-scores">
        <h3>Cluster Scores Details</h3>
        <ul>
          {Object.entries(testResult.scores.cluster_scores || {}).map(([cluster, scores]) => (
            <li key={cluster}>
              <strong>{cluster}:</strong> Average: {scores.average}, Total: {scores.total}
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}

export default TestResultView;
```

---

## 🔧 Customization Tips

### Change Colors
Modify the `backgroundColor` and `borderColor` in the backend response, or override in frontend:

```javascript
const customChartData = {
  ...chartData,
  datasets: [{
    ...chartData.datasets[0],
    backgroundColor: 'rgba(255, 99, 132, 0.2)',
    borderColor: 'rgba(255, 99, 132, 1)',
  }]
};
```

### Multiple Datasets (Compare Results)
```javascript
// Add another dataset for comparison
const chartData = {
  labels: radarData.labels,
  datasets: [
    radarData.datasets[0], // Current result
    {
      label: 'Previous Result',
      data: [3.5, 4.0, 3.8, 4.2],
      backgroundColor: 'rgba(255, 99, 132, 0.2)',
      borderColor: 'rgba(255, 99, 132, 1)',
      borderWidth: 2
    }
  ]
};
```

---

## 📝 Notes

- The radar chart data is automatically calculated from cluster scores
- Data format is compatible with Chart.js out of the box
- All endpoints that return test results now include `radar_chart` field
- The `maxValue` field helps set appropriate scale limits
- Chart uses average cluster scores for visualization

