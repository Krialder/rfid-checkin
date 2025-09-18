# Metrics Directory

This directory contains performance metrics, analytics data, and monitoring information collected from the RFID Check-in System. These metrics provide insights into system performance, user behavior, and operational efficiency.

## 📁 Directory Structure

```
metrics/
├── performance_2025-09-16.json   # Daily performance metrics
├── analytics_YYYY-MM-DD.json     # Daily analytics data (when generated)
├── error_logs_YYYY-MM-DD.json    # Error tracking data (when generated)
└── usage_stats_YYYY-MM-DD.json   # Usage statistics (when generated)
```

## 📊 Performance Metrics

### performance_YYYY-MM-DD.json

**Purpose**: Tracks real-time performance metrics including execution times, memory usage, database queries, and cache operations.

**Sample Data Structure:**
```json
{
    "execution_time": 0.012524127960205078,
    "memory_usage": 2097152,
    "memory_peak": 2097152,
    "memory_delta": 0,
    "queries_executed": 0,
    "cache_operations": 0,
    "cache_stats": {
        "hits": 0,
        "misses": 0,
        "writes": 0,
        "deletes": 0
    },
    "slow_queries": [],
    "cache_hit_ratio": 0,
    "timestamp": 1758008427,
    "page": "unknown",
    "user_agent": "unknown"
}
```

**Metrics Collected:**

| Metric | Description | Unit | Significance |
|--------|-------------|------|--------------|
| **execution_time** | Page generation time | Seconds | Response speed |
| **memory_usage** | Current memory consumption | Bytes | Resource efficiency |
| **memory_peak** | Peak memory during request | Bytes | Memory optimization |
| **memory_delta** | Memory change during request | Bytes | Memory leaks detection |
| **queries_executed** | Database queries count | Integer | Database load |
| **cache_operations** | Cache interaction count | Integer | Cache efficiency |
| **cache_hit_ratio** | Cache success percentage | Float (0-1) | Cache performance |
| **slow_queries** | Queries exceeding threshold | Array | Query optimization |

### Data Collection Process

**Automatic Collection:**
```php
<?php
// In core/PerformanceManager.php

class PerformanceManager {
    private $startTime;
    private $startMemory;
    private $queryCount = 0;
    private $cacheStats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0
    ];
    
    public function startTracking() {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
    }
    
    public function endTracking() {
        $metrics = [
            'execution_time' => microtime(true) - $this->startTime,
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
            'memory_delta' => memory_get_usage() - $this->startMemory,
            'queries_executed' => $this->queryCount,
            'cache_operations' => array_sum($this->cacheStats),
            'cache_stats' => $this->cacheStats,
            'cache_hit_ratio' => $this->calculateCacheHitRatio(),
            'slow_queries' => $this->getSlowQueries(),
            'timestamp' => time(),
            'page' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $this->saveMetrics($metrics);
    }
    
    private function saveMetrics($metrics) {
        $filename = 'metrics/performance_' . date('Y-m-d') . '.json';
        
        // Load existing data
        $existingData = [];
        if (file_exists($filename)) {
            $existingData = json_decode(file_get_contents($filename), true) ?: [];
        }
        
        // Append new metrics
        $existingData[] = $metrics;
        
        // Save updated data
        file_put_contents($filename, json_encode($existingData, JSON_PRETTY_PRINT));
    }
}
?>
```

## 📈 Analytics Metrics

### analytics_YYYY-MM-DD.json

**Purpose**: Tracks user behavior, feature usage, and business metrics.

**Data Structure:**
```json
{
    "daily_stats": {
        "total_checkins": 150,
        "unique_users": 45,
        "active_events": 3,
        "device_scans": 142,
        "manual_checkins": 8
    },
    "hourly_breakdown": {
        "00": {"checkins": 0, "users": 0},
        "08": {"checkins": 25, "users": 15},
        "12": {"checkins": 40, "users": 25},
        "17": {"checkins": 35, "users": 20}
    },
    "popular_features": {
        "dashboard": 89,
        "event_details": 67,
        "analytics_view": 23,
        "profile_settings": 12
    },
    "device_usage": {
        "RFID_READER_001": 78,
        "RFID_READER_002": 64,
        "manual_entry": 8
    },
    "timestamp": 1758008427,
    "generated_by": "analytics_service"
}
```

**Key Performance Indicators (KPIs):**
- **User Engagement**: Daily active users, session duration
- **System Adoption**: Feature usage patterns, user retention
- **Operational Efficiency**: Check-in success rates, error rates
- **Device Performance**: RFID reader utilization, response times

## 🚨 Error Tracking

### error_logs_YYYY-MM-DD.json

**Purpose**: Comprehensive error tracking and analysis for system stability.

**Error Data Structure:**
```json
{
    "timestamp": 1758008427,
    "error_type": "DatabaseException",
    "severity": "ERROR",
    "message": "Connection timeout to database",
    "file": "/core/database.php",
    "line": 45,
    "user_id": 123,
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "request_uri": "/api/rfid-checkin.php",
    "stack_trace": "...",
    "context": {
        "query": "SELECT * FROM users WHERE rfid_tag = ?",
        "parameters": ["ABC123"]
    },
    "resolution_status": "pending"
}
```

**Error Categories:**
- **Database Errors**: Connection issues, query failures
- **Authentication Errors**: Login failures, permission denied
- **RFID Hardware Errors**: Device communication failures
- **API Errors**: Request validation, rate limiting
- **Security Events**: Suspicious activity, attack attempts

## 📊 Usage Statistics

### usage_stats_YYYY-MM-DD.json

**Purpose**: Detailed usage patterns and system utilization metrics.

**Usage Data:**
```json
{
    "page_views": {
        "dashboard.php": 234,
        "events.php": 156,
        "check-ins.php": 89,
        "analytics.php": 45
    },
    "api_calls": {
        "rfid-checkin.php": 142,
        "dashboard.php": 89,
        "event-details.php": 67
    },
    "user_sessions": {
        "total_sessions": 78,
        "average_duration": 1260,
        "bounce_rate": 0.12
    },
    "geographic_data": {
        "local_network": 95,
        "remote_access": 12
    },
    "device_types": {
        "desktop": 67,
        "mobile": 23,
        "tablet": 8
    }
}
```

## 🔧 Metrics Management

### Data Collection Configuration

**Metrics Configuration:**
```php
<?php
// In core/config.php

// Performance monitoring settings
define('METRICS_ENABLED', true);
define('METRICS_DIRECTORY', __DIR__ . '/../metrics/');
define('METRICS_RETENTION_DAYS', 90);

// Performance thresholds
define('SLOW_QUERY_THRESHOLD', 0.5); // 500ms
define('MEMORY_LIMIT_WARNING', 64 * 1024 * 1024); // 64MB
define('CACHE_HIT_RATIO_WARNING', 0.8); // 80%

// Analytics settings
define('ANALYTICS_ENABLED', true);
define('TRACK_USER_BEHAVIOR', true);
define('ANONYMIZE_IP_ADDRESSES', true);
?>
```

### Automated Data Cleanup

**Retention Policy:**
```php
<?php
// In core/MetricsManager.php

class MetricsManager {
    public function cleanupOldMetrics() {
        $retentionDays = METRICS_RETENTION_DAYS;
        $cutoffDate = date('Y-m-d', strtotime("-{$retentionDays} days"));
        
        $metricsDir = METRICS_DIRECTORY;
        $files = glob($metricsDir . '*.json');
        
        foreach ($files as $file) {
            if (preg_match('/(\d{4}-\d{2}-\d{2})\.json$/', $file, $matches)) {
                $fileDate = $matches[1];
                if ($fileDate < $cutoffDate) {
                    unlink($file);
                    error_log("Deleted old metrics file: " . basename($file));
                }
            }
        }
    }
    
    public function archiveMetrics($startDate, $endDate) {
        $archiveName = "metrics_archive_{$startDate}_to_{$endDate}.zip";
        $zip = new ZipArchive();
        
        if ($zip->open($archiveName, ZipArchive::CREATE) === TRUE) {
            $files = glob(METRICS_DIRECTORY . "*.json");
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
            return $archiveName;
        }
        return false;
    }
}
?>
```

## 📊 Metrics Analysis

### Performance Analysis

**Performance Trends:**
```php
<?php
// Generate performance reports
class PerformanceAnalyzer {
    public function generateDailyReport($date) {
        $filename = "metrics/performance_{$date}.json";
        if (!file_exists($filename)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($filename), true);
        
        return [
            'average_response_time' => $this->calculateAverage($data, 'execution_time'),
            'peak_memory_usage' => max(array_column($data, 'memory_peak')),
            'total_queries' => array_sum(array_column($data, 'queries_executed')),
            'cache_efficiency' => $this->calculateAverage($data, 'cache_hit_ratio'),
            'slow_query_count' => $this->countSlowQueries($data),
            'recommendations' => $this->generateRecommendations($data)
        ];
    }
    
    private function generateRecommendations($data) {
        $recommendations = [];
        
        $avgResponseTime = $this->calculateAverage($data, 'execution_time');
        if ($avgResponseTime > 0.5) {
            $recommendations[] = "Consider optimizing slow queries and implementing caching";
        }
        
        $cacheHitRatio = $this->calculateAverage($data, 'cache_hit_ratio');
        if ($cacheHitRatio < 0.8) {
            $recommendations[] = "Improve cache strategy to increase hit ratio";
        }
        
        return $recommendations;
    }
}
?>
```

### Real-time Monitoring

**Live Metrics Dashboard:**
```javascript
// Frontend metrics visualization
class MetricsDashboard {
    constructor() {
        this.metricsEndpoint = '/api/metrics.php';
        this.updateInterval = 30000; // 30 seconds
        this.initializeCharts();
        this.startRealTimeUpdates();
    }
    
    async fetchLatestMetrics() {
        try {
            const response = await fetch(this.metricsEndpoint);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Failed to fetch metrics:', error);
            return null;
        }
    }
    
    updatePerformanceChart(data) {
        // Update performance visualization
        const performanceChart = this.charts.performance;
        performanceChart.data.labels.push(new Date().toLocaleTimeString());
        performanceChart.data.datasets[0].data.push(data.execution_time);
        
        // Keep only last 20 data points
        if (performanceChart.data.labels.length > 20) {
            performanceChart.data.labels.shift();
            performanceChart.data.datasets[0].data.shift();
        }
        
        performanceChart.update();
    }
    
    startRealTimeUpdates() {
        setInterval(async () => {
            const metrics = await this.fetchLatestMetrics();
            if (metrics) {
                this.updateAllCharts(metrics);
            }
        }, this.updateInterval);
    }
}
```

## 🔒 Security and Privacy

### Data Protection

**Privacy Considerations:**
- IP address anonymization for user privacy
- User ID hashing for analytics
- Secure storage of sensitive metrics
- Access control for metrics data

**Security Measures:**
```php
<?php
// Secure metrics access
class MetricsAuthManager {
    public function canAccessMetrics($user) {
        return Auth::hasRole(['admin', 'analyst']) && 
               Auth::hasPermission('view_metrics');
    }
    
    public function sanitizeMetricsData($data) {
        // Remove or anonymize sensitive information
        if (isset($data['user_agent'])) {
            $data['user_agent'] = $this->anonymizeUserAgent($data['user_agent']);
        }
        
        if (isset($data['ip_address'])) {
            $data['ip_address'] = $this->anonymizeIP($data['ip_address']);
        }
        
        return $data;
    }
    
    private function anonymizeIP($ip) {
        $parts = explode('.', $ip);
        return implode('.', array_slice($parts, 0, 3)) . '.XXX';
    }
}
?>
```

## 📈 Reporting and Visualization

### Automated Reports

**Daily Summary Reports:**
```php
<?php
// Generate daily metrics summary
class MetricsReporter {
    public function generateDailySummary($date) {
        $report = [
            'date' => $date,
            'performance' => $this->getPerformanceSummary($date),
            'usage' => $this->getUsageSummary($date),
            'errors' => $this->getErrorSummary($date),
            'recommendations' => $this->getRecommendations($date)
        ];
        
        // Save report
        $filename = "reports/daily_summary_{$date}.json";
        file_put_contents($filename, json_encode($report, JSON_PRETTY_PRINT));
        
        // Send email notification if configured
        if (METRICS_EMAIL_REPORTS) {
            $this->emailReport($report);
        }
        
        return $report;
    }
}
?>
```

### Visualization Tools

**Chart.js Integration:**
```html
<!DOCTYPE html>
<html>
<head>
    <title>Metrics Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="metrics-dashboard">
        <canvas id="performanceChart"></canvas>
        <canvas id="usageChart"></canvas>
        <canvas id="errorChart"></canvas>
    </div>
    
    <script>
        // Performance metrics chart
        const performanceCtx = document.getElementById('performanceChart').getContext('2d');
        const performanceChart = new Chart(performanceCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Response Time (ms)',
                    data: [],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
```

---

**Metrics System**: Production Ready  
**Data Retention**: 90 days configurable  
**Privacy**: GDPR compliant with anonymization  
**Last Updated**: January 2025