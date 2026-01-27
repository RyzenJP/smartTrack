# SmartTrack Testing Documentation

Comprehensive testing guide for the SmartTrack Vehicle Tracking System.

## Table of Contents

1. [Setup](#setup)
2. [Running Tests](#running-tests)
3. [Test Types](#test-types)
4. [Writing Tests](#writing-tests)
5. [Coverage Reports](#coverage-reports)
6. [CI/CD Integration](#cicd-integration)

---

## Setup

### Prerequisites

- PHP 7.4 or higher
- PHPUnit 9.5 or higher
- MySQL/MariaDB for integration tests
- Composer (recommended)

### Installation

```bash
# Install PHPUnit via Composer (recommended)
composer require --dev phpunit/phpunit ^9.5

# Or download PHPUnit PHAR
wget https://phar.phpunit.de/phpunit-9.phar
chmod +x phpunit-9.phar
mv phpunit-9.phar /usr/local/bin/phpunit
```

### Test Database Setup

Create a separate test database:

```sql
CREATE DATABASE test_smarttrack;
CREATE USER 'test_user'@'localhost' IDENTIFIED BY 'test_password';
GRANT ALL PRIVILEGES ON test_smarttrack.* TO 'test_user'@'localhost';
FLUSH PRIVILEGES;
```

Update `phpunit.xml` with your test database credentials.

---

## Running Tests

### Run All Tests

```bash
# Using Composer
composer test

# Using PHPUnit directly
phpunit

# Or
php vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
# Run only unit tests
phpunit --testsuite "Unit Tests"

# Run only integration tests
phpunit --testsuite "Integration Tests"

# Run only feature tests
phpunit --testsuite "Feature Tests"
```

### Run Specific Test File

```bash
phpunit tests/Unit/SecurityTest.php
```

### Run Specific Test Method

```bash
phpunit --filter testSanitizeInputString
phpunit tests/Unit/SecurityTest.php::testSanitizeInputString
```

### Run with Coverage

```bash
# Generate HTML coverage report
phpunit --coverage-html tests/coverage

# Generate text coverage report
phpunit --coverage-text

# Generate Clover XML (for CI)
phpunit --coverage-clover tests/coverage.xml
```

---

## Test Types

### 1. Unit Tests (`tests/Unit/`)

Test individual components in isolation.

**Example**: SecurityTest.php
- Tests Security class methods
- Tests input sanitization
- Tests CSRF token generation

**Example**: CacheHelperTest.php
- Tests cache operations
- Tests expiration
- Tests data integrity

### 2. Integration Tests (`tests/Integration/`)

Test interaction between components.

**Example**: DatabaseTest.php
- Tests database connectivity
- Tests CRUD operations
- Tests transactions
- Tests SQL injection prevention

### 3. Feature/E2E Tests (`tests/Feature/`)

Test complete user workflows.

**Example**: APIEndpointTest.php
- Tests API endpoints
- Tests authentication
- Tests error handling

---

## Writing Tests

### Unit Test Template

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    private $instance;

    protected function setUp(): void
    {
        // Setup before each test
        $this->instance = new ExampleClass();
    }

    protected function tearDown(): void
    {
        // Cleanup after each test
        $this->instance = null;
    }

    public function testExample()
    {
        // Arrange
        $input = 'test';
        
        // Act
        $result = $this->instance->method($input);
        
        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### Integration Test Template

```php
<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class DatabaseIntegrationTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        $this->conn = setupTestDatabase();
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            $this->conn->close();
        }
    }

    public function testDatabaseOperation()
    {
        // Test database interaction
        $stmt = $this->conn->prepare("SELECT * FROM table");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $this->assertGreaterThan(0, $result->num_rows);
    }
}
```

---

## Coverage Reports

### Generating Coverage

```bash
# HTML report (most detailed)
phpunit --coverage-html tests/coverage

# Open in browser
open tests/coverage/index.html  # macOS
xdg-open tests/coverage/index.html  # Linux
start tests/coverage/index.html  # Windows
```

### Coverage Metrics

- **Line Coverage**: Percentage of lines executed
- **Function Coverage**: Percentage of functions called
- **Class Coverage**: Percentage of classes used
- **Branch Coverage**: Percentage of branches taken

### Target Coverage

- **Critical Components**: 90%+ coverage
- **Core Logic**: 80%+ coverage
- **Utility Functions**: 70%+ coverage
- **Overall Project**: 60%+ coverage

---

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '7.4'
        extensions: mysqli, pdo_mysql
        coverage: xdebug
    
    - name: Install dependencies
      run: composer install
    
    - name: Run tests
      run: phpunit --coverage-clover coverage.xml
    
    - name: Upload coverage
      uses: codecov/codecov-action@v2
      with:
        files: ./coverage.xml
```

---

## Best Practices

1. **Test Naming**: Use descriptive names that explain what is being tested
2. **AAA Pattern**: Arrange, Act, Assert
3. **One Assertion**: Each test should verify one thing
4. **Independence**: Tests should not depend on each other
5. **Fast Tests**: Keep tests fast to encourage frequent running
6. **Clean Up**: Always clean up test data
7. **Mock External Services**: Don't test external APIs directly
8. **Test Edge Cases**: Test boundary conditions and error cases

---

## Troubleshooting

### Common Issues

**Tests fail to connect to database**
- Check test database credentials in `phpunit.xml`
- Ensure test database exists
- Verify user permissions

**Coverage reports not generated**
- Install Xdebug: `pecl install xdebug`
- Enable Xdebug in php.ini

**Tests run slowly**
- Reduce test database size
- Use database transactions for faster rollback
- Mock heavy operations

---

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Test-Driven Development Guide](https://martinfowler.com/bliki/TestDrivenDevelopment.html)
- [PHP Testing Best Practices](https://phptherightway.com/#testing)

---

## Continuous Improvement

- Add tests for new features
- Increase coverage gradually
- Review and update tests regularly
- Share testing knowledge with team
- Automate test execution in CI/CD

