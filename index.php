<?php
session_start();
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

$error = '';
$success = '';
$columns = [];
$previewData = [];
$fileName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $uploadedFile = $_FILES['file'];
    
    if ($uploadedFile['error'] === UPLOAD_ERR_OK) {
        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['xls', 'xlsx', 'csv'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            try {
                $filePath = $uploadedFile['tmp_name'];
                
                if ($fileExtension === 'csv') {
                    $reader = new Csv();
                } elseif ($fileExtension === 'xls') {
                    $reader = new Xls();
                } else {
                    $reader = new Xlsx();
                }
                
                $spreadsheet = $reader->load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $data = $worksheet->toArray();
                
                if (!empty($data)) {
                    $columns = array_shift($data);
                    
                    $_SESSION['file_data'] = $data;
                    $_SESSION['file_columns'] = $columns;
                    $_SESSION['file_name'] = $uploadedFile['name'];
                    
                    $previewData = array_slice($data, 0, 10);
                    $fileName = $uploadedFile['name'];
                    $success = 'File uploaded successfully! Total rows: ' . count($data);
                } else {
                    $error = 'The file appears to be empty.';
                }
            } catch (Exception $e) {
                $error = 'Error reading file: ' . $e->getMessage();
            }
        } else {
            $error = 'Invalid file type. Please upload XLS, XLSX, or CSV files only.';
        }
    } else {
        $error = 'Error uploading file. Please try again.';
    }
} elseif (isset($_SESSION['file_columns'])) {
    $columns = $_SESSION['file_columns'];
    $fileName = $_SESSION['file_name'] ?? '';
    $previewData = array_slice($_SESSION['file_data'] ?? [], 0, 10);
}

if (isset($_POST['clear_session'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XLS/XLSX/CSV Processor</title>
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
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border-left: 4px solid #3c3;
        }
        
        .upload-section {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            transition: all 0.3s;
        }
        
        .upload-section:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .upload-section input[type="file"] {
            display: none;
        }
        
        .upload-label {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .upload-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group select,
        .form-group input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-group select:focus,
        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            cursor: pointer;
            font-weight: 500;
        }
        
        .preview-section {
            margin-top: 30px;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        
        .preview-section h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .preview-table th,
        .preview-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .preview-table th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        
        .preview-table tr:hover {
            background: #f8f9fa;
        }
        
        .file-info {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .file-info strong {
            color: #1976D2;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .options-grid {
                grid-template-columns: 1fr;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 XLS/XLSX/CSV Processor</h1>
            <p>Upload and process your spreadsheet files with ease</p>
        </div>
        
        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($fileName)): ?>
                <div class="file-info">
                    <strong>📁 Current File:</strong> <?php echo htmlspecialchars($fileName); ?>
                </div>
            <?php endif; ?>
            
            <div class="upload-section">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <h3 style="margin-bottom: 15px; color: #333;">Upload Your File</h3>
                    <p style="color: #666; margin-bottom: 20px;">Supported formats: XLS, XLSX, CSV</p>
                    <label for="file" class="upload-label">
                        📤 Choose File
                    </label>
                    <input type="file" name="file" id="file" accept=".xls,.xlsx,.csv" required onchange="this.form.submit()">
                    <p id="fileName" style="margin-top: 15px; color: #666;"></p>
                </form>
            </div>
            
            <?php if (!empty($columns)): ?>
                <form method="POST" action="processor.php" id="processForm">
                    <div class="form-group">
                        <label for="column">Select Column to Process</label>
                        <select name="column" id="column" required>
                            <option value="">-- Select a column --</option>
                            <?php foreach ($columns as $index => $column): ?>
                                <option value="<?php echo $index; ?>">
                                    <?php echo htmlspecialchars($column ?: "Column " . ($index + 1)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Processing Options</label>
                        <div class="options-grid">
                            <div class="checkbox-group">
                                <input type="checkbox" name="remove_duplicates" id="remove_duplicates" value="1">
                                <label for="remove_duplicates">Remove Duplicates</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" name="trim_spaces" id="trim_spaces" value="1" checked>
                                <label for="trim_spaces">Trim Spaces</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" name="to_lowercase" id="to_lowercase" value="1">
                                <label for="to_lowercase">Convert to Lowercase</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" name="to_uppercase" id="to_uppercase" value="1">
                                <label for="to_uppercase">Convert to Uppercase</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="predefined_functions">Predefined Functions (Optional)</label>
                        <select name="predefined_functions" id="predefined_functions">
                            <option value="">-- None --</option>
                            <option value="trim">trim - Remove whitespace</option>
                            <option value="strtolower">strtolower - Convert to lowercase</option>
                            <option value="strtoupper">strtoupper - Convert to uppercase</option>
                            <option value="md5">md5 - Generate MD5 hash</option>
                            <option value="sha1">sha1 - Generate SHA1 hash</option>
                            <option value="base64_encode">base64_encode - Encode to Base64</option>
                            <option value="urlencode">urlencode - URL encode</option>
                            <option value="json_encode">json_encode - Convert to JSON</option>
                        </select>
                        <small style="color: #666; display: block; margin-top: 5px;">💡 Select a predefined function or use custom function below</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="function_pipeline">Function Pipeline (Advanced)</label>
                        <input type="text" name="function_pipeline" id="function_pipeline" placeholder="e.g., trim | strtolower | md5">
                        <small style="color: #666; display: block; margin-top: 5px;">⚡ Chain multiple functions using | separator (executes left to right)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="custom_function">Custom PHP Function (Advanced)
                            <button type="button" class="btn-help" onclick="toggleHelp()" style="margin-left: 10px; padding: 4px 12px; font-size: 12px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer;">📖 Help</button>
                        </label>
                        <textarea name="custom_function" id="custom_function" rows="8" style="width: 100%; padding: 12px; border: 2px solid #dee2e6; border-radius: 6px; font-family: 'Consolas', 'Monaco', monospace; font-size: 13px; resize: vertical;" placeholder="function customTransform($value) {
    // Your custom logic here
    return trim(strtolower($value));
}"></textarea>
                        <small style="color: #666; display: block; margin-top: 5px;">⚙️ Define a custom function that receives $value and returns the processed value</small>
                    </div>
                    
                    <div id="helpSection" style="display: none; background: #e3f2fd; border-left: 4px solid #2196F3; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                        <h4 style="color: #1976D2; margin-bottom: 15px;">� Custom Function Guide</h4>
                        
                        <div style="margin-bottom: 15px;">
                            <strong>Basic Structure:</strong>
                            <pre style="background: #fff; padding: 12px; border-radius: 4px; margin-top: 8px; overflow-x: auto;">function customTransform($value) {
    // Your processing logic
    return $processedValue;
}</pre>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <strong>Example 1 - Extract Domain from Email:</strong>
                            <pre style="background: #fff; padding: 12px; border-radius: 4px; margin-top: 8px; overflow-x: auto;">function customTransform($value) {
    $parts = explode('@', $value);
    return isset($parts[1]) ? $parts[1] : $value;
}</pre>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <strong>Example 2 - Format Phone Number:</strong>
                            <pre style="background: #fff; padding: 12px; border-radius: 4px; margin-top: 8px; overflow-x: auto;">function customTransform($value) {
    $clean = preg_replace('/[^0-9]/', '', $value);
    return preg_replace('/^(\d{3})(\d{3})(\d{4})$/', '($1) $2-$3', $clean);
}</pre>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <strong>Example 3 - Add Prefix/Suffix:</strong>
                            <pre style="background: #fff; padding: 12px; border-radius: 4px; margin-top: 8px; overflow-x: auto;">function customTransform($value) {
    return 'PREFIX_' . $value . '_SUFFIX';
}</pre>
                        </div>
                        
                        <div style="background: #fff3cd; padding: 12px; border-radius: 4px; border-left: 4px solid #ffc107;">
                            <strong>⚠️ Important Notes:</strong>
                            <ul style="margin: 8px 0 0 20px;">
                                <li>Function name must be <code>customTransform</code></li>
                                <li>Must accept one parameter: <code>$value</code></li>
                                <li>Must return the processed value</li>
                                <li>Errors will be caught and displayed safely</li>
                                <li>Pipeline and predefined functions execute before custom function</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">�🚀 Process Data</button>
                        <button type="button" class="btn btn-secondary" onclick="clearSession()">🔄 Upload New File</button>
                    </div>
                </form>
                
                <?php if (!empty($previewData)): ?>
                    <div class="preview-section">
                        <h3>📋 Preview (First 10 Rows)</h3>
                        <div style="overflow-x: auto;">
                            <table class="preview-table">
                                <thead>
                                    <tr>
                                        <?php foreach ($columns as $column): ?>
                                            <th><?php echo htmlspecialchars($column ?: 'Column'); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($previewData as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $cell): ?>
                                                <td><?php echo htmlspecialchars($cell ?? ''); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <form id="clearForm" method="POST" style="display: none;">
        <input type="hidden" name="clear_session" value="1">
    </form>
    
    <script>
        document.getElementById('file').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                document.getElementById('fileName').textContent = '📄 Selected: ' + fileName;
            }
        });
        
        function clearSession() {
            if (confirm('This will clear the current file and start over. Continue?')) {
                document.getElementById('clearForm').submit();
            }
        }
        
        document.getElementById('to_lowercase')?.addEventListener('change', function() {
            if (this.checked) {
                document.getElementById('to_uppercase').checked = false;
            }
        });
        
        document.getElementById('to_uppercase')?.addEventListener('change', function() {
            if (this.checked) {
                document.getElementById('to_lowercase').checked = false;
            }
        });
        
        function toggleHelp() {
            const helpSection = document.getElementById('helpSection');
            if (helpSection.style.display === 'none') {
                helpSection.style.display = 'block';
            } else {
                helpSection.style.display = 'none';
            }
        }
        
        document.getElementById('predefined_functions')?.addEventListener('change', function() {
            const value = this.value;
            const pipelineInput = document.getElementById('function_pipeline');
            if (value && pipelineInput) {
                if (pipelineInput.value) {
                    pipelineInput.value += ' | ' + value;
                } else {
                    pipelineInput.value = value;
                }
                this.value = '';
            }
        });
    </script>
</body>
</html>
