<?php
session_start();
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;

if (!isset($_SESSION['file_data']) || !isset($_SESSION['file_columns'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['column'])) {
    header('Location: index.php');
    exit;
}

$columnIndex = (int)$_POST['column'];
$data = $_SESSION['file_data'];
$columns = $_SESSION['file_columns'];
$fileName = $_SESSION['file_name'] ?? 'file';

$removeDuplicates = isset($_POST['remove_duplicates']);
$trimSpaces = isset($_POST['trim_spaces']);
$toLowerCase = isset($_POST['to_lowercase']);
$toUpperCase = isset($_POST['to_uppercase']);
$functionPipeline = trim($_POST['function_pipeline'] ?? '');
$customFunction = trim($_POST['custom_function'] ?? '');

$functionErrors = [];
$customFunctionDefined = false;

if (!empty($customFunction)) {
    $validationResult = validateAndDefineCustomFunction($customFunction);
    if ($validationResult['success']) {
        $customFunctionDefined = true;
    } else {
        $functionErrors[] = $validationResult['error'];
    }
}

$originalValues = [];
$processedValues = [];
$processingErrors = [];

foreach ($data as $rowIndex => $row) {
    if (isset($row[$columnIndex])) {
        $originalValue = $row[$columnIndex];
        
        try {
            $processedValue = processValue(
                $originalValue, 
                $trimSpaces, 
                $toLowerCase, 
                $toUpperCase,
                $functionPipeline,
                $customFunctionDefined
            );
            
            $originalValues[] = $originalValue;
            $processedValues[] = $processedValue;
        } catch (Exception $e) {
            $processingErrors[] = "Row " . ($rowIndex + 1) . ": " . $e->getMessage();
            $originalValues[] = $originalValue;
            $processedValues[] = '[ERROR]';
        }
    }
}

if ($removeDuplicates) {
    $processedValues = array_unique($processedValues);
    $processedValues = array_values($processedValues);
}

$totalRows = count($processedValues);
$selectedColumn = $columns[$columnIndex] ?? "Column " . ($columnIndex + 1);

function validateAndDefineCustomFunction($code) {
    $code = trim($code);
    
    if (strpos($code, 'function customTransform') === false) {
        return [
            'success' => false,
            'error' => 'Custom function must be named "customTransform"'
        ];
    }
    
    if (strpos($code, '<?php') !== false || strpos($code, '?>') !== false) {
        return [
            'success' => false,
            'error' => 'Do not include PHP tags in custom function'
        ];
    }
    
    $dangerousFunctions = ['eval', 'exec', 'system', 'passthru', 'shell_exec', 'popen', 'proc_open', 'pcntl_exec'];
    foreach ($dangerousFunctions as $func) {
        if (stripos($code, $func) !== false) {
            return [
                'success' => false,
                'error' => 'Dangerous function "' . $func . '" is not allowed'
            ];
        }
    }
    
    try {
        @eval($code);
        
        if (!function_exists('customTransform')) {
            return [
                'success' => false,
                'error' => 'Function "customTransform" was not properly defined'
            ];
        }
        
        $testResult = customTransform('test');
        
        return ['success' => true];
    } catch (ParseError $e) {
        return [
            'success' => false,
            'error' => 'Syntax error: ' . $e->getMessage()
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ];
    }
}

function executePipeline($value, $pipeline) {
    $functions = array_map('trim', explode('|', $pipeline));
    
    foreach ($functions as $func) {
        if (empty($func)) continue;
        
        $allowedFunctions = [
            'trim', 'strtolower', 'strtoupper', 'md5', 'sha1', 
            'base64_encode', 'base64_decode', 'urlencode', 'urldecode',
            'json_encode', 'htmlspecialchars', 'strip_tags', 'ucfirst',
            'ucwords', 'lcfirst', 'strrev', 'strlen'
        ];
        
        if (!in_array($func, $allowedFunctions)) {
            throw new Exception("Function '{$func}' is not allowed in pipeline");
        }
        
        if (!function_exists($func)) {
            throw new Exception("Function '{$func}' does not exist");
        }
        
        $value = $func($value);
    }
    
    return $value;
}

function processValue($value, $trim = true, $lower = false, $upper = false, $pipeline = '', $useCustom = false) {
    if ($trim) {
        $value = trim($value);
    }
    
    if ($lower) {
        $value = mb_strtolower($value);
    } elseif ($upper) {
        $value = mb_strtoupper($value);
    }
    
    if (!empty($pipeline)) {
        $value = executePipeline($value, $pipeline);
    }
    
    if ($useCustom && function_exists('customTransform')) {
        $value = customTransform($value);
    }
    
    return $value;
}

function generatePhpArrayRows($values) {
    $output = "\$data = [\n";
    foreach ($values as $value) {
        $escapedValue = addslashes($value);
        $output .= "    ['{$escapedValue}'],\n";
    }
    $output .= "];";
    return $output;
}

function generatePhpArrayFlat($values) {
    $output = "\$values = [\n";
    foreach ($values as $value) {
        $escapedValue = addslashes($value);
        $output .= "    '{$escapedValue}',\n";
    }
    $output .= "];";
    return $output;
}

function generateMySQLInClause($values, $columnName = 'column_name') {
    $escapedValues = array_map(function($value) {
        return "'" . addslashes($value) . "'";
    }, $values);
    
    return "WHERE {$columnName} IN (" . implode(', ', $escapedValues) . ")";
}

function generateCSVFile($values, $fileName) {
    $downloadsDir = __DIR__ . '/downloads';
    if (!is_dir($downloadsDir)) {
        mkdir($downloadsDir, 0755, true);
    }
    
    $csvFileName = 'processed_' . date('Y-m-d_His') . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($fileName, PATHINFO_FILENAME)) . '.csv';
    $csvFilePath = $downloadsDir . '/' . $csvFileName;
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    $sheet->setCellValue('A1', 'Processed Values');
    
    $row = 2;
    foreach ($values as $value) {
        $sheet->setCellValue('A' . $row, $value);
        $row++;
    }
    
    $writer = new CsvWriter($spreadsheet);
    $writer->save($csvFilePath);
    
    return $csvFileName;
}

$phpArrayRows = generatePhpArrayRows($processedValues);
$phpArrayFlat = generatePhpArrayFlat($processedValues);
$mysqlInClause = generateMySQLInClause($processedValues);
$csvFileName = generateCSVFile($processedValues, $fileName);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Results - XLS/XLSX/CSV Processor</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .content {
            padding: 30px;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
        }
        
        .info-box h3 {
            color: #1976D2;
            margin-bottom: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-item {
            background: white;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #bbdefb;
        }
        
        .info-item strong {
            color: #1976D2;
            display: block;
            margin-bottom: 5px;
            font-size: 0.9em;
        }
        
        .info-item span {
            color: #333;
            font-size: 1.1em;
        }
        
        .accordion {
            margin-bottom: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .accordion:hover {
            border-color: #667eea;
        }
        
        .accordion-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
            transition: all 0.2s;
        }
        
        .accordion-header:hover {
            background: linear-gradient(135deg, #5568d3 0%, #653a8b 100%);
        }
        
        .accordion-header h3 {
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .accordion-toggle {
            font-size: 1.5em;
            transition: transform 0.3s;
        }
        
        .accordion.active .accordion-toggle {
            transform: rotate(180deg);
        }
        
        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            background: #f8f9fa;
        }
        
        .accordion.active .accordion-content {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .accordion-body {
            padding: 20px;
        }
        
        .output-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-copy {
            background: #4CAF50;
            color: white;
        }
        
        .btn-copy:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
        }
        
        .btn-copy.copied {
            background: #2196F3;
        }
        
        .btn-download {
            background: #FF9800;
            color: white;
        }
        
        .btn-download:hover {
            background: #F57C00;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.4);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .code-output {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 6px;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            overflow-x: auto;
            resize: vertical;
            min-height: 200px;
            max-height: 500px;
            overflow-y: auto;
            white-space: pre;
        }
        
        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 6px;
            overflow: hidden;
            margin-top: 15px;
        }
        
        .comparison-table th,
        .comparison-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .comparison-table th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        
        .comparison-table tr:hover {
            background: #f5f5f5;
        }
        
        .comparison-table td:first-child {
            color: #666;
        }
        
        .comparison-table td:last-child {
            color: #2196F3;
            font-weight: 500;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 30px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .back-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .preview-limit {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            color: #856404;
        }
        
        @media (max-width: 768px) {
            .output-controls {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Processing Complete</h1>
            <p>Your data has been processed successfully</p>
        </div>
        
        <div class="content">
            <?php if (!empty($functionErrors)): ?>
                <div style="background: #fee; color: #c33; border-left: 4px solid #c33; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                    <h3 style="margin-bottom: 10px;">❌ Custom Function Errors</h3>
                    <ul style="margin-left: 20px;">
                        <?php foreach ($functionErrors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($processingErrors)): ?>
                <div style="background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                    <h3 style="margin-bottom: 10px;">⚠️ Processing Warnings</h3>
                    <p style="margin-bottom: 10px;">Some rows encountered errors during processing:</p>
                    <ul style="margin-left: 20px; max-height: 200px; overflow-y: auto;">
                        <?php foreach (array_slice($processingErrors, 0, 10) as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                        <?php if (count($processingErrors) > 10): ?>
                            <li><em>... and <?php echo count($processingErrors) - 10; ?> more errors</em></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h3>📊 Processing Summary</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Selected Column</strong>
                        <span><?php echo htmlspecialchars($selectedColumn); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Total Rows Processed</strong>
                        <span><?php echo number_format($totalRows); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Duplicates Removed</strong>
                        <span><?php echo $removeDuplicates ? 'Yes' : 'No'; ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Basic Transformations</strong>
                        <span>
                            <?php 
                            $transforms = [];
                            if ($trimSpaces) $transforms[] = 'Trim';
                            if ($toLowerCase) $transforms[] = 'Lowercase';
                            if ($toUpperCase) $transforms[] = 'Uppercase';
                            echo $transforms ? implode(', ', $transforms) : 'None';
                            ?>
                        </span>
                    </div>
                    <?php if (!empty($functionPipeline)): ?>
                    <div class="info-item">
                        <strong>Function Pipeline</strong>
                        <span><?php echo htmlspecialchars($functionPipeline); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($customFunctionDefined): ?>
                    <div class="info-item">
                        <strong>Custom Function</strong>
                        <span style="color: #4CAF50;">✓ Active</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($customFunctionDefined && !empty($customFunction)): ?>
            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <h3>⚙️ Custom Function Code</h3>
                    <span class="accordion-toggle">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <div class="output-controls">
                            <button class="btn btn-copy" onclick="copyToClipboard('customFunctionCode', this)">
                                📋 Copy Function
                            </button>
                        </div>
                        <div class="code-output" id="customFunctionCode"><?php echo htmlspecialchars($customFunction); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="accordion active">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <h3>🔍 Value Comparison (Original vs Processed)</h3>
                    <span class="accordion-toggle">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <?php if (count($processedValues) > 50): ?>
                            <div class="preview-limit">
                                ⚠️ Showing first 50 rows only (Total: <?php echo count($processedValues); ?> rows)
                            </div>
                        <?php endif; ?>
                        <div style="overflow-x: auto;">
                            <table class="comparison-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Original Value</th>
                                        <th>Processed Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $displayLimit = min(50, count($processedValues));
                                    for ($i = 0; $i < $displayLimit; $i++): 
                                    ?>
                                        <tr>
                                            <td><?php echo $i + 1; ?></td>
                                            <td><?php echo htmlspecialchars($originalValues[$i] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($processedValues[$i]); ?></td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <h3>📝 PHP Array (Row Format)</h3>
                    <span class="accordion-toggle">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <div class="output-controls">
                            <button class="btn btn-copy" onclick="copyToClipboard('output1', this)">
                                📋 Copy to Clipboard
                            </button>
                        </div>
                        <div class="code-output" id="output1"><?php echo htmlspecialchars($phpArrayRows); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <h3>📝 PHP Array (Flat Format)</h3>
                    <span class="accordion-toggle">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <div class="output-controls">
                            <button class="btn btn-copy" onclick="copyToClipboard('output2', this)">
                                📋 Copy to Clipboard
                            </button>
                        </div>
                        <div class="code-output" id="output2"><?php echo htmlspecialchars($phpArrayFlat); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <h3>🗄️ MySQL IN Clause</h3>
                    <span class="accordion-toggle">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <div class="output-controls">
                            <button class="btn btn-copy" onclick="copyToClipboard('output3', this)">
                                📋 Copy to Clipboard
                            </button>
                        </div>
                        <div class="code-output" id="output3"><?php echo htmlspecialchars($mysqlInClause); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <h3>📥 CSV Export</h3>
                    <span class="accordion-toggle">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <div class="output-controls">
                            <a href="downloads/<?php echo htmlspecialchars($csvFileName); ?>" download class="btn btn-download">
                                ⬇️ Download CSV File
                            </a>
                        </div>
                        <p style="color: #666; margin-top: 15px;">
                            <strong>File:</strong> <?php echo htmlspecialchars($csvFileName); ?><br>
                            <strong>Rows:</strong> <?php echo number_format($totalRows); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <a href="index.php" class="back-link">← Process Another File</a>
        </div>
    </div>
    
    <script>
        function toggleAccordion(header) {
            const accordion = header.parentElement;
            const wasActive = accordion.classList.contains('active');
            
            accordion.classList.toggle('active');
        }
        
        function copyToClipboard(elementId, button) {
            const element = document.getElementById(elementId);
            const text = element.textContent;
            
            // Try modern clipboard API first
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    showCopySuccess(button);
                }).catch(function(err) {
                    fallbackCopyToClipboard(text, button);
                });
            } else {
                // Fallback for older browsers or non-HTTPS contexts
                fallbackCopyToClipboard(text, button);
            }
        }
        
        function fallbackCopyToClipboard(text, button) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showCopySuccess(button);
            } catch (err) {
                alert('Failed to copy: ' + err);
            }
            
            document.body.removeChild(textArea);
        }
        
        function showCopySuccess(button) {
            const originalText = button.innerHTML;
            button.innerHTML = '✅ Copied!';
            button.classList.add('copied');
            
            setTimeout(function() {
                button.innerHTML = originalText;
                button.classList.remove('copied');
            }, 2000);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const codeOutputs = document.querySelectorAll('.code-output');
            codeOutputs.forEach(function(output) {
                output.style.height = 'auto';
                const scrollHeight = output.scrollHeight;
                if (scrollHeight > 200) {
                    output.style.height = '300px';
                }
            });
        });
    </script>
</body>
</html>
