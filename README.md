# XLS/XLSX/CSV Processor Tool

A production-ready PHP web application for processing Excel and CSV files with multiple output formats.

## Features

### 📤 File Upload
- Support for `.xls`, `.xlsx`, and `.csv` files
- Automatic file type detection
- Uses PhpSpreadsheet library for reliable Excel parsing

### 🎯 Column Selection
- Displays all detected columns from uploaded file
- Select any single column for processing
- Preview first 10 rows before processing

### ⚙️ Processing Options
- **Remove Duplicates**: Eliminate duplicate values from results
- **Trim Spaces**: Remove leading/trailing whitespace
- **Convert to Lowercase**: Transform all text to lowercase
- **Convert to Uppercase**: Transform all text to uppercase

### � Advanced Function Support (NEW!)

#### Predefined Functions Library
Select from commonly used functions:
- `trim` - Remove whitespace
- `strtolower` / `strtoupper` - Case conversion
- `md5` / `sha1` - Hash generation
- `base64_encode` - Base64 encoding
- `urlencode` - URL encoding
- `json_encode` - JSON conversion

#### Function Pipeline
Chain multiple functions using `|` separator:
```
trim | strtolower | md5
```
Functions execute left to right.

#### Custom PHP Functions
Write your own transformation logic:
```php
function customTransform($value) {
    // Extract domain from email
    $parts = explode('@', $value);
    return isset($parts[1]) ? $parts[1] : $value;
}
```

**Features:**
- Safe execution with error handling
- Syntax validation before processing
- Comprehensive error messages
- Security restrictions on dangerous functions
- Execution order: Basic transforms → Pipeline → Custom function

**See [CUSTOM_FUNCTIONS_GUIDE.md](CUSTOM_FUNCTIONS_GUIDE.md) for detailed examples and documentation.**

### �📊 Multiple Output Formats

#### A. PHP Array (Row Format)
```php
$data = [
    ['value1'],
    ['value2'],
    ['value3'],
];
```

#### B. PHP Array (Flat Format)
```php
$values = [
    'value1',
    'value2',
    'value3',
];
```

#### C. MySQL IN Clause
```sql
WHERE column_name IN ('value1', 'value2', 'value3')
```

#### D. CSV Export
- Downloadable CSV file with processed results
- Timestamped filename for easy organization

### 🎨 User Interface
- Clean, modern design with gradient styling
- Collapsible accordion sections for each output
- Copy to clipboard functionality for all text outputs
- Resizable text areas
- Responsive design for mobile devices
- Value comparison table (original vs processed)

## Installation

### Requirements
- PHP 7.4 or higher
- Composer
- Web server (Apache, Nginx, or Laragon)

### Setup Steps

1. **Clone or download the project**
   ```bash
   git clone https://github.com/HasnBatal/Sheet-Wizard.git
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Set permissions** (if on Linux/Mac)
   ```bash
   chmod 755 downloads/
   ```

4. **Access the application**
   - Open your browser
   - Navigate to: `http://localhost/SheetWizard/`

## Usage

### Step 1: Upload File
1. Click "Choose File" button
2. Select your `.xls`, `.xlsx`, or `.csv` file
3. File will be automatically uploaded and parsed

### Step 2: Configure Processing
1. Select the column you want to process from the dropdown
2. Choose processing options:
   - ✅ Remove Duplicates (optional)
   - ✅ Trim Spaces (enabled by default)
   - ✅ Convert to Lowercase (optional)
   - ✅ Convert to Uppercase (optional)
3. Review the preview table showing first 10 rows

### Step 3: Process Data
1. Click "🚀 Process Data" button
2. View results in organized accordion sections
3. Compare original vs processed values
4. Copy outputs to clipboard or download CSV

### Step 4: Use Outputs
- Expand any accordion section to view output
- Click "📋 Copy to Clipboard" to copy code
- Click "⬇️ Download CSV File" to download processed data
- Click "← Process Another File" to start over

## Custom Processing Functions

### Using the UI (Recommended)

Define custom functions directly in the web interface:

1. **Select Predefined Functions** - Choose from dropdown and add to pipeline
2. **Build Function Pipeline** - Chain functions like `trim | strtolower | md5`
3. **Write Custom Function** - Add your own PHP logic in the textarea

**Example Custom Function:**
```php
function customTransform($value) {
    // Format phone number
    $clean = preg_replace('/[^0-9]/', '', $value);
    return preg_replace('/^(\d{3})(\d{3})(\d{4})$/', '($1) $2-$3', $clean);
}
```

### Advanced: Modifying Core Logic

You can also customize the base `processValue()` function in `processor.php`:

```php
function processValue($value, $trim = true, $lower = false, $upper = false, $pipeline = '', $useCustom = false) {
    // Basic transformations
    if ($trim) $value = trim($value);
    if ($lower) $value = mb_strtolower($value);
    elseif ($upper) $value = mb_strtoupper($value);
    
    // Pipeline execution
    if (!empty($pipeline)) $value = executePipeline($value, $pipeline);
    
    // Custom function
    if ($useCustom && function_exists('customTransform')) {
        $value = customTransform($value);
    }
    
    return $value;
}
```

### Function Examples

See [CUSTOM_FUNCTIONS_GUIDE.md](CUSTOM_FUNCTIONS_GUIDE.md) for 10+ ready-to-use examples:
- Extract email domains
- Format phone numbers
- Generate slugs
- Mask sensitive data
- Date formatting
- And more!

## File Structure

```
SheetWizard/
├── index.php                    # Main upload and configuration page
├── processor.php                # Processing logic and results display
├── composer.json                # Dependency management
├── .gitignore                  # Git ignore rules
├── README.md                   # This file
├── CUSTOM_FUNCTIONS_GUIDE.md   # Detailed custom function documentation
├── sample_data.csv             # Sample test data
├── vendor/                     # PhpSpreadsheet and dependencies
│   └── phpoffice/
│       └── phpspreadsheet/
└── downloads/                  # Generated CSV files
    └── .htaccess               # Prevent directory listing
```

## Security Features

- Session-based file handling (no files stored on server)
- Input validation and sanitization
- File type verification
- Protected downloads directory
- Proper error handling
- **Custom function security:**
  - Blocked dangerous functions (eval, exec, system, etc.)
  - Whitelisted pipeline functions only
  - Syntax validation before execution
  - Safe error handling prevents crashes
  - Test execution before processing data

## Browser Compatibility

- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Mobile browsers

## Troubleshooting

### "Error reading file"
- Ensure the file is a valid Excel or CSV file
- Check file is not corrupted
- Try re-saving the file in Excel

### "File appears to be empty"
- Verify the file contains data
- Check that the first row contains column headers

### CSV download not working
- Ensure `downloads/` directory exists and is writable
- Check PHP has permission to write files

### Copy to clipboard not working
- Use HTTPS or localhost (required for clipboard API)
- Try a different browser
- Manually select and copy the text

## Technical Details

### Dependencies
- **phpoffice/phpspreadsheet**: ^1.29
  - Handles Excel and CSV file reading/writing
  - Supports XLS, XLSX, and CSV formats

### PHP Extensions Required
- php-zip
- php-xml
- php-gd (optional, for better performance)

## Customization

### Add Custom Functions via UI

**No code changes needed!** Use the web interface:
1. Upload your file
2. Select column
3. Write custom function in textarea or use pipeline
4. Process and see results

### Modify Output Formats
Edit the generator functions in `processor.php`:
- `generatePhpArrayRows()` - Row format array
- `generatePhpArrayFlat()` - Flat array
- `generateMySQLInClause()` - SQL IN clause
- `generateCSVFile()` - CSV export

### Add New Output Format
1. Create a new generator function in `processor.php`
2. Generate the output after processing
3. Add a new accordion section in the HTML
4. Include copy/download functionality

### Extend Pipeline Functions
Add more allowed functions in `processor.php`:
```php
$allowedFunctions = [
    'trim', 'strtolower', 'strtoupper', 'md5', 'sha1',
    'your_new_function_here'
];
```

### Styling
- Modify CSS in `<style>` sections of `index.php` and `processor.php`
- Current theme uses purple gradient (#667eea to #764ba2)
- Fully responsive with mobile-first approach

## License

This project is open-source and available for personal and commercial use.

## Support

For issues or questions:
1. Check the troubleshooting section
2. Verify all requirements are met
3. Review PHP error logs
4. Ensure file permissions are correct

## Version History

### v2.0.0 (2026-03-08)
- **NEW: Custom PHP function support**
- **NEW: Predefined functions library**
- **NEW: Function pipeline execution**
- **NEW: Comprehensive function documentation**
- Enhanced error handling with detailed messages
- Security validation for custom code
- Function execution preview
- Custom function code display in results

### v1.0.0 (2026-03-08)
- Initial release
- Support for XLS, XLSX, CSV files
- Multiple output formats
- Modern UI with accordion interface
- Copy to clipboard functionality
- CSV export feature
- Value comparison table
- Processing options (trim, case conversion, deduplication)
