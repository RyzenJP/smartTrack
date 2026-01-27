# SmartTrack Coding Standards

This document outlines the coding standards and best practices for the SmartTrack project.

## PSR-12 Compliance

This project follows the [PSR-12 Extended Coding Style Guide](https://www.php-fig.org/psr/psr-12/).

### Key Standards

#### 1. File Formatting
- Files MUST use only `<?php` tags (no closing `?>` tag)
- Files MUST use UTF-8 encoding without BOM
- Files MUST use Unix LF line endings
- Files MUST end with a single blank line

#### 2. Indentation and Spacing
- Code MUST use 4 spaces for indentation, MUST NOT use tabs
- There MUST NOT be trailing whitespace at the end of lines
- Blank lines MAY be added to improve readability

#### 3. Naming Conventions
- Class names MUST be declared in `StudlyCaps`
- Method names MUST be declared in `camelCase`
- Constants MUST be declared in all upper case with underscore separators
- Variable names MUST be declared in `camelCase`

#### 4. Control Structures
- Control structure keywords MUST have one space after them
- Opening braces MUST be on the same line
- Closing braces MUST be on their own line
- There MUST be one space after control structure keywords
- There MUST NOT be a space after the opening parenthesis
- There MUST NOT be a space before the closing parenthesis

Example:
```php
if ($condition) {
    // code
} elseif ($otherCondition) {
    // code
} else {
    // code
}
```

#### 5. Maximum Nesting Level
- Maximum nesting level SHOULD NOT exceed 4 levels
- Deeply nested code SHOULD be refactored into separate methods

#### 6. Line Length
- Soft limit: 120 characters
- Hard limit: 150 characters
- Long lines SHOULD be broken into multiple lines

#### 7. Function and Method Declarations
- There MUST NOT be a space after the opening parenthesis
- There MUST NOT be a space before the closing parenthesis
- Arguments MUST be separated by a single space
- Default values MUST be at the end of the argument list

Example:
```php
public function example($arg1, $arg2 = null)
{
    // code
}
```

#### 8. Arrays
- Short array syntax MUST be used: `[]` instead of `array()`
- Array keys MUST be quoted if they are strings
- Trailing commas are allowed in multi-line arrays

Example:
```php
$array = [
    'key1' => 'value1',
    'key2' => 'value2',
];
```

#### 9. Comments
- All comments MUST be written in English
- DocBlocks MUST be used for classes, methods, and properties
- Inline comments SHOULD be used sparingly and explain "why" not "what"

#### 10. Database Queries
- Prepared statements MUST be used for all queries with user input
- Query strings SHOULD be formatted for readability
- Long queries SHOULD be broken into multiple lines

Example:
```php
$stmt = $pdo->prepare("
    SELECT id, name, email
    FROM users
    WHERE status = ? AND role = ?
    ORDER BY name ASC
");
$stmt->execute([$status, $role]);
```

## Code Quality Guidelines

### Refactoring Deep Nesting

If code has more than 4 levels of nesting, consider:

1. **Extract Methods**: Break complex logic into smaller methods
2. **Early Returns**: Use early returns to reduce nesting
3. **Guard Clauses**: Check conditions early and return if not met
4. **Strategy Pattern**: Use design patterns to simplify complex conditionals

Example of refactoring:
```php
// Before (deeply nested)
if ($condition1) {
    if ($condition2) {
        if ($condition3) {
            if ($condition4) {
                // do something
            }
        }
    }
}

// After (using early returns)
if (!$condition1) {
    return;
}
if (!$condition2) {
    return;
}
if (!$condition3) {
    return;
}
if (!$condition4) {
    return;
}
// do something
```

### Consistent Formatting

1. **Spacing**: Use consistent spacing around operators
2. **Braces**: Always use braces for control structures, even single-line
3. **Alignment**: Align similar code blocks for readability
4. **Grouping**: Group related code together

## Tools

### PHP_CodeSniffer
Use PHP_CodeSniffer to check code style:
```bash
phpcs --standard=PSR12 path/to/file.php
```

### PHP-CS-Fixer
Use PHP-CS-Fixer to automatically fix code style:
```bash
php-cs-fixer fix --rules=@PSR12 path/to/file.php
```

## Enforcement

- Code reviews MUST check for PSR-12 compliance
- New code MUST follow these standards
- Existing code SHOULD be gradually refactored to meet standards

