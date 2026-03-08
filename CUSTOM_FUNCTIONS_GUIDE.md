# Custom Functions Guide

## Overview

The XLS/XLSX/CSV Processor now supports **custom PHP functions** that allow you to apply any transformation logic to your data. This guide explains how to use this powerful feature safely and effectively.

## Features

### 1. Predefined Functions Library

Choose from a dropdown of commonly used functions:

- **trim** - Remove leading/trailing whitespace
- **strtolower** - Convert to lowercase
- **strtoupper** - Convert to uppercase  
- **md5** - Generate MD5 hash
- **sha1** - Generate SHA1 hash
- **base64_encode** - Encode to Base64
- **urlencode** - URL encode
- **json_encode** - Convert to JSON

### 2. Function Pipeline

Chain multiple functions together using the `|` separator. Functions execute from left to right.

**Examples:**
```
trim | strtolower
trim | strtolower | md5
base64_encode | urlencode
```

### 3. Custom PHP Functions

Write your own PHP function to perform any transformation you need.

## Writing Custom Functions

### Basic Structure

```php
function customTransform($value) {
    // Your processing logic here
    return $processedValue;
}
```

### Requirements

1. **Function name** must be exactly `customTransform`
2. Must accept **one parameter**: `$value`
3. Must **return** the processed value
4. Do **not** include PHP tags (`<?php` or `?>`)

### Execution Order

When you use multiple features together, they execute in this order:

1. Basic transformations (trim, lowercase/uppercase checkboxes)
2. Function pipeline
3. Custom function
4. Duplicate removal (if enabled)

## Examples

### Example 1: Extract Domain from Email

```php
function customTransform($value) {
    $parts = explode('@', $value);
    return isset($parts[1]) ? $parts[1] : $value;
}
```

**Input:** `john@example.com`  
**Output:** `example.com`

### Example 2: Format Phone Numbers

```php
function customTransform($value) {
    // Remove all non-numeric characters
    $clean = preg_replace('/[^0-9]/', '', $value);
    
    // Format as (XXX) XXX-XXXX
    if (strlen($clean) === 10) {
        return preg_replace('/^(\d{3})(\d{3})(\d{4})$/', '($1) $2-$3', $clean);
    }
    
    return $value;
}
```

**Input:** `555-123-4567`  
**Output:** `(555) 123-4567`

### Example 3: Add Prefix and Suffix

```php
function customTransform($value) {
    return 'USER_' . $value . '_2026';
}
```

**Input:** `john123`  
**Output:** `USER_john123_2026`

### Example 4: Extract First Name

```php
function customTransform($value) {
    $parts = explode(' ', trim($value));
    return $parts[0];
}
```

**Input:** `John Doe`  
**Output:** `John`

### Example 5: Generate Slug

```php
function customTransform($value) {
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}
```

**Input:** `Hello World! 2026`  
**Output:** `hello-world-2026`

### Example 6: Conditional Transformation

```php
function customTransform($value) {
    if (is_numeric($value)) {
        return '$' . number_format($value, 2);
    }
    return $value;
}
```

**Input:** `1234.5`  
**Output:** `$1,234.50`

### Example 7: Extract File Extension

```php
function customTransform($value) {
    $ext = pathinfo($value, PATHINFO_EXTENSION);
    return strtolower($ext);
}
```

**Input:** `document.PDF`  
**Output:** `pdf`

### Example 8: Mask Sensitive Data

```php
function customTransform($value) {
    if (strlen($value) > 4) {
        return str_repeat('*', strlen($value) - 4) . substr($value, -4);
    }
    return $value;
}
```

**Input:** `1234567890`  
**Output:** `******7890`

### Example 9: Convert to Title Case

```php
function customTransform($value) {
    return ucwords(strtolower($value));
}
```

**Input:** `HELLO WORLD`  
**Output:** `Hello World`

### Example 10: Remove Special Characters

```php
function customTransform($value) {
    return preg_replace('/[^a-zA-Z0-9\s]/', '', $value);
}
```

**Input:** `Hello@World#2026!`  
**Output:** `HelloWorld2026`

## Combining Features

You can combine all features for powerful transformations:

### Example: Clean and Hash Email

**Configuration:**
- Trim Spaces: ✅ Enabled
- Pipeline: `strtolower | md5`
- Custom Function: None

**Input:** `  John@Example.COM  `  
**Output:** `7c6a180b36896a0a8c02787eeafb0e4c` (MD5 of "john@example.com")

### Example: Extract and Format

**Configuration:**
- Pipeline: `trim`
- Custom Function:
```php
function customTransform($value) {
    // Extract domain and uppercase
    $parts = explode('@', $value);
    $domain = isset($parts[1]) ? $parts[1] : $value;
    return strtoupper($domain);
}
```

**Input:** `user@example.com`  
**Output:** `EXAMPLE.COM`

## Security Features

The system includes multiple security measures:

### Blocked Functions

These dangerous functions are **not allowed**:
- `eval`
- `exec`
- `system`
- `passthru`
- `shell_exec`
- `popen`
- `proc_open`
- `pcntl_exec`

### Pipeline Whitelist

Only these functions are allowed in pipelines:
- `trim`, `strtolower`, `strtoupper`
- `md5`, `sha1`
- `base64_encode`, `base64_decode`
- `urlencode`, `urldecode`
- `json_encode`
- `htmlspecialchars`, `strip_tags`
- `ucfirst`, `ucwords`, `lcfirst`
- `strrev`, `strlen`

### Validation

All custom functions are:
1. Syntax-checked before execution
2. Test-executed with sample data
3. Wrapped in error handlers
4. Prevented from breaking the application

## Error Handling

If your custom function has errors, you'll see:

### Syntax Errors
```
Syntax error: syntax error, unexpected 'return' (T_RETURN)
```

### Runtime Errors
```
Error: Call to undefined function someFunction()
```

### Validation Errors
```
Custom function must be named "customTransform"
```

All errors are displayed clearly without breaking the page.

## Best Practices

1. **Test with small datasets first** - Verify your function works before processing large files
2. **Handle edge cases** - Check for empty values, null, unexpected formats
3. **Use built-in functions** - PHP has many useful string/array functions
4. **Keep it simple** - Complex logic can be broken into pipeline steps
5. **Return consistent types** - Always return a string value
6. **Add comments** - Document what your function does

## Troubleshooting

### Function not executing
- Check function name is exactly `customTransform`
- Ensure you're returning a value
- Verify no syntax errors

### Unexpected results
- Check execution order (basic → pipeline → custom)
- Test function in isolation
- Verify input data format

### Performance issues
- Avoid complex regex on large datasets
- Use built-in functions when possible
- Consider pipeline instead of custom function

## Advanced Use Cases

### Working with JSON Data

```php
function customTransform($value) {
    $data = json_decode($value, true);
    if (isset($data['email'])) {
        return $data['email'];
    }
    return $value;
}
```

### Date Formatting

```php
function customTransform($value) {
    $timestamp = strtotime($value);
    if ($timestamp) {
        return date('Y-m-d', $timestamp);
    }
    return $value;
}
```

### Concatenate Multiple Fields

While custom functions receive one value at a time, you can work with delimited data:

```php
function customTransform($value) {
    // Assuming value is "FirstName|LastName"
    $parts = explode('|', $value);
    return implode(' ', $parts);
}
```

## Support

For more examples and help:
1. Click the "📖 Help" button in the interface
2. Review the examples in the help section
3. Start with simple transformations and build up
4. Check the error messages for guidance

---

**Remember:** Custom functions give you unlimited power to transform your data exactly how you need it!
