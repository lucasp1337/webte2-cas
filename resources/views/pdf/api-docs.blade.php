<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('pdf.title') }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #1a1a1a;
            background: #fff;
        }

        h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 6px;
        }

        h2 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #444;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 4px;
        }

        .cover {
            padding: 40px 0 24px;
            border-bottom: 2px solid #1a1a1a;
            margin-bottom: 28px;
        }

        .cover .version {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }

        .cover .description {
            font-size: 12px;
            color: #555;
            margin-top: 10px;
        }

        .toc {
            margin-bottom: 32px;
        }

        .toc table {
            width: 100%;
            border-collapse: collapse;
        }

        .toc td {
            padding: 4px 6px;
            border-bottom: 1px solid #f0f0f0;
        }

        .toc td:first-child {
            width: 70%;
        }

        .route-block {
            page-break-inside: avoid;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            margin-bottom: 20px;
            padding: 14px 16px;
        }

        .route-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .method-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #fff;
        }

        .method-GET    { background: #4caf50; }
        .method-POST   { background: #2196f3; }
        .method-PUT    { background: #ff9800; }
        .method-PATCH  { background: #ff9800; }
        .method-DELETE { background: #f44336; }
        .method-HEAD   { background: #9c27b0; }

        .route-path {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .route-summary {
            font-size: 11px;
            color: #555;
            margin-bottom: 10px;
        }

        .section-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #888;
            margin-bottom: 4px;
            margin-top: 10px;
        }

        table.params {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        table.params th {
            text-align: left;
            padding: 3px 6px;
            background: #f7f7f7;
            border-bottom: 1px solid #ddd;
            font-weight: 600;
            color: #555;
        }

        table.params td {
            padding: 3px 6px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: top;
        }

        table.params td code {
            background: #f0f0f0;
            padding: 0 3px;
            border-radius: 2px;
            font-family: 'Courier New', Courier, monospace;
        }

        .response-row {
            display: flex;
            gap: 8px;
            padding: 3px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 10px;
        }

        .response-code {
            font-family: 'Courier New', Courier, monospace;
            font-weight: 700;
            min-width: 40px;
        }

        .code-2xx { color: #4caf50; }
        .code-4xx { color: #ff9800; }
        .code-5xx { color: #f44336; }

        pre.body-schema {
            background: #f7f7f7;
            border: 1px solid #e0e0e0;
            border-radius: 3px;
            padding: 8px;
            font-size: 10px;
            font-family: 'Courier New', Courier, monospace;
            white-space: pre-wrap;
            word-break: break-all;
        }
    </style>
</head>
<body>

    {{-- Cover block --}}
    <div class="cover">
        <h1>{{ __('pdf.title') }}</h1>
        @if (!empty($spec['info']['version']))
            <div class="version">v{{ $spec['info']['version'] }}</div>
        @endif
        @if (!empty($spec['info']['description']))
            <div class="description">{{ $spec['info']['description'] }}</div>
        @endif
    </div>

    {{-- Table of contents --}}
    @php
        /** @var array<string, array<string, mixed>> $paths */
        $paths = $spec['paths'] ?? [];
    @endphp

    @if (!empty($paths))
        <h2>{{ __('pdf.route') }}</h2>
        <div class="toc">
            <table>
                @foreach ($paths as $path => $methods)
                    @foreach ($methods as $method => $operation)
                        @if (is_array($operation))
                            <tr>
                                <td>
                                    <span class="method-badge method-{{ strtoupper((string)$method) }}">{{ strtoupper((string)$method) }}</span>
                                    <code>{{ $path }}</code>
                                </td>
                                <td>{{ $operation['summary'] ?? '' }}</td>
                            </tr>
                        @endif
                    @endforeach
                @endforeach
            </table>
        </div>
    @endif

    {{-- Per-route detail blocks --}}
    @foreach ($paths as $path => $methods)
        @foreach ($methods as $method => $operation)
            @if (is_array($operation))
                @php
                    /** @var array<string, mixed> $operation */
                    $params = $operation['parameters'] ?? [];
                    $requestBody = $operation['requestBody'] ?? null;
                    $responses = $operation['responses'] ?? [];
                    $security = $operation['security'] ?? null;
                @endphp
                <div class="route-block">
                    <div class="route-header">
                        <span class="method-badge method-{{ strtoupper((string)$method) }}">{{ strtoupper((string)$method) }}</span>
                        <span class="route-path">{{ $path }}</span>
                    </div>

                    @if (!empty($operation['summary']))
                        <div class="route-summary">{{ $operation['summary'] }}</div>
                    @endif

                    @if (!empty($operation['description']) && $operation['description'] !== ($operation['summary'] ?? ''))
                        <div class="route-summary">{{ $operation['description'] }}</div>
                    @endif

                    {{-- Parameters --}}
                    @if (!empty($params))
                        <div class="section-label">{{ __('pdf.parameters') }}</div>
                        <table class="params">
                            <thead>
                                <tr>
                                    <th>{{ __('pdf.param_name') }}</th>
                                    <th>{{ __('pdf.param_in') }}</th>
                                    <th>{{ __('pdf.param_type') }}</th>
                                    <th>{{ __('pdf.param_required') }}</th>
                                    <th>{{ __('pdf.description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($params as $param)
                                    @if (is_array($param))
                                        @php
                                            /** @var array<string, mixed> $param */
                                            $schema = $param['schema'] ?? [];
                                            $rawType = is_array($schema) ? ($schema['type'] ?? '-') : '-';
                                            $type = is_array($rawType) ? implode('|', array_filter(array_map('strval', $rawType), fn (string $v) => $v !== 'null')) : (is_string($rawType) ? $rawType : '-');
                                        @endphp
                                        <tr>
                                            <td><code>{{ $param['name'] ?? '' }}</code></td>
                                            <td>{{ $param['in'] ?? '' }}</td>
                                            <td>{{ $type }}</td>
                                            <td>{{ !empty($param['required']) ? '✓' : '' }}</td>
                                            <td>{{ is_string($param['description'] ?? '') ? ($param['description'] ?? '') : '' }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    {{-- Request body --}}
                    @if (!empty($requestBody) && is_array($requestBody))
                        <div class="section-label">{{ __('pdf.requestBody') }}</div>
                        @php
                            /** @var array<string, mixed> $requestBody */
                            $content = $requestBody['content'] ?? [];
                            $jsonSchema = is_array($content) && isset($content['application/json'])
                                ? (is_array($content['application/json']) ? ($content['application/json']['schema'] ?? null) : null)
                                : null;
                        @endphp
                        @if (!empty($jsonSchema))
                            <pre class="body-schema">{{ json_encode($jsonSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        @endif
                    @endif

                    {{-- Responses --}}
                    @if (!empty($responses) && is_array($responses))
                        <div class="section-label">{{ __('pdf.responses') }}</div>
                        @foreach ($responses as $statusCode => $response)
                            @if (is_array($response))
                                @php
                                    /** @var array<string, mixed> $response */
                                    $codeClass = str_starts_with((string)$statusCode, '2') ? 'code-2xx'
                                        : (str_starts_with((string)$statusCode, '4') ? 'code-4xx' : 'code-5xx');
                                @endphp
                                <div class="response-row">
                                    <span class="response-code {{ $codeClass }}">{{ $statusCode }}</span>
                                    <span>{{ $response['description'] ?? '' }}</span>
                                </div>
                            @endif
                        @endforeach
                    @endif

                    {{-- Security --}}
                    @if (!empty($security) && is_array($security))
                        <div class="section-label">{{ __('pdf.security') }}</div>
                        @foreach ($security as $scheme)
                            @if (is_array($scheme))
                                @foreach (array_keys($scheme) as $schemeName)
                                    <div class="route-summary">{{ $schemeName }}</div>
                                @endforeach
                            @endif
                        @endforeach
                    @endif
                </div>
            @endif
        @endforeach
    @endforeach

</body>
</html>
