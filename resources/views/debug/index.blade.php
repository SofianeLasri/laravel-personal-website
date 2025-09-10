<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Information - {{ config('app.name') }}</title>
    <style>
        body {
            font-family: monospace;
            background: #f5f5f5;
            margin: 20px;
            line-height: 1.6;
        }
        h1, h2 {
            color: #333;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }
        .section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            text-align: left;
            padding: 8px;
            border: 1px solid #ddd;
        }
        th {
            background: #f0f0f0;
            font-weight: bold;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            color: #856404;
        }
        .success {
            color: #155724;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .error {
            color: #721c24;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .small {
            font-size: 0.9em;
            color: #666;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .stat-box {
            background: #f9f9f9;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }
        .stat-label {
            font-weight: bold;
            color: #666;
            font-size: 0.9em;
        }
        .stat-value {
            font-size: 1.5em;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="warning">
        <strong>⚠️ Debug Mode</strong> - This page is only visible in non-production environments.
        <br>Current Environment: <strong>{{ config('app.env') }}</strong>
    </div>

    <h1 id="debug-title">🔍 Debug Information</h1>
    
    <!-- Model Statistics -->
    <div class="section" id="model-statistics">
        <h2>📊 Model Statistics</h2>
        <div class="stats-grid">
            @foreach($modelStats as $model => $count)
                <div class="stat-box">
                    <div class="stat-label">{{ $model }}</div>
                    <div class="stat-value">{{ $count }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Environment Variables -->
    <div class="section" id="env-vars">
        <h2>⚙️ Environment Variables</h2>
        <table>
            <tr>
                <th>Variable</th>
                <th>Value</th>
            </tr>
            @foreach($envVars as $key => $value)
                <tr>
                    <td>{{ $key }}</td>
                    <td>
                        @if(is_bool($value))
                            {{ $value ? 'true' : 'false' }}
                        @else
                            {{ $value ?? 'null' }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

    <!-- Database Information -->
    <div class="section" id="db-info">
        <h2>🗄️ Database Information</h2>
        <table>
            <tr>
                <th>Property</th>
                <th>Value</th>
            </tr>
            @foreach($dbInfo as $key => $value)
                <tr>
                    <td>{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                    <td>{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    <!-- Storage Information -->
    <div class="section" id="storage-info">
        <h2>📁 Storage Information</h2>
        <table>
            <tr>
                <th>Property</th>
                <th>Value</th>
            </tr>
            @foreach($storageInfo as $key => $value)
                <tr>
                    <td>{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                    <td>
                        @if(is_bool($value))
                            @if($value)
                                <span class="success">✓ Yes</span>
                            @else
                                <span class="error">✗ No</span>
                            @endif
                        @else
                            {{ $value }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

    <!-- Routes -->
    <div class="section" id="routes">
        <h2>🛣️ Routes (First 30)</h2>
        <table>
            <tr>
                <th>Methods</th>
                <th>URI</th>
                <th>Name</th>
                <th>Action</th>
            </tr>
            @foreach($routes as $route)
                <tr>
                    <td><small>{{ $route['methods'] }}</small></td>
                    <td><code>{{ $route['uri'] }}</code></td>
                    <td><small>{{ $route['name'] ?? '-' }}</small></td>
                    <td><small>{{ Str::limit($route['action'], 50) }}</small></td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="section" id="quick-actions">
        <h2>💡 Quick Actions</h2>
        <ul>
            <li><a href="/projects" target="_blank">View Projects Page</a></li>
            <li><a href="/dashboard" target="_blank">View Dashboard</a></li>
            <li><a href="/horizon" target="_blank">Laravel Horizon </a></li>
        </ul>
    </div>

    <div class="small" style="text-align: center; margin-top: 40px; color: #999;">
        Generated at {{ now()->format('Y-m-d H:i:s') }} | 
        PHP {{ PHP_VERSION }} | 
        Laravel {{ app()->version() }}
    </div>
</body>
</html>