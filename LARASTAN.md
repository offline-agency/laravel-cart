# Larastan Integration Guide

## Overview

Larastan has been successfully installed and configured for the laravel-cart package. It found **20 errors** in the codebase that have been baselined for now.

## Configuration

- **Configuration file**: `phpstan.neon`
- **Baseline file**: `phpstan-baseline.neon`
- **Analysis level**: 5 (out of 9)
- **Analyzed paths**: `src/`, `config/`, `database/`

## Commands

### Run static analysis
```bash
composer phpstan
```

### Regenerate baseline
After fixing errors, update the baseline:
```bash
composer phpstan:baseline
```

### Run with different options
```bash
# Show progress
vendor/bin/phpstan analyse --memory-limit=2G -v

# Clear cache
vendor/bin/phpstan clear-result-cache
```

## Current Issues Found (Baselined)

The baseline contains 20 errors across the following categories:

### 1. **Unused Code** (2 errors)
- `CanBeBought` trait is unused
- `Cart::numberFormat()` method is unused

### 2. **Type Mismatches** (10 errors)
PHPDoc types don't match native types for parameters in `Cart::add()`:
- `$name`, `$qty`, `$price`, `$vat`, `$totalPrice`, `$subtitle`, `$urlImg`, `$productFcCode`, `$vatFcCode`

### 3. **Property Issues** (2 errors)
- `Cart::$options` property is only written, never read
- `CartItem::$priceTax` property is undefined

### 4. **Logic Issues** (5 errors in CartItem.php)
- Redundant `is_numeric()` checks on float/int types
- Impossible comparison (`int<1,max> < 0` is always false)
- Type error: passing float to `strlen()` which expects string
- Always false boolean OR operation

### 5. **Documentation Issues** (1 error)
- Invalid PHPDoc `@var` tag in `Cart.php`

## Recommended Next Steps

### 1. Increase Analysis Level Gradually
Start at level 5, fix issues, then increase:
```neon
parameters:
    level: 6  # Increase gradually to 9
```

### 2. Fix Type Mismatches
Update method signatures to match PHPDoc or vice versa:
```php
// Before
public function add(string $name, int $qty, float $price, ...)

// If parameters can be null, update signature:
public function add(string $name, ?int $qty = null, ?float $price = null, ...)
```

### 3. Remove Unused Code
- Remove `CanBeBought` trait if not used
- Remove `numberFormat()` method if not needed

### 4. Fix Property Issues
- Add `$priceTax` property to `CartItem` class
- Use `$options` property or remove it

### 5. Fix Logic Issues in CartItem
- Remove redundant `is_numeric()` checks
- Fix type issues with `strlen()`
- Review boolean logic

## CI/CD Integration

Add to your GitHub Actions workflow:

```yaml
- name: Run Larastan
  run: composer phpstan
```

## Tips

1. **Don't commit the baseline to production** - Use it as a temporary measure
2. **Fix errors incrementally** - Don't try to fix everything at once
3. **Run before commits** - Add to pre-commit hooks
4. **Update regularly** - Keep Larastan updated for new checks

## Resources

- [Larastan Documentation](https://github.com/larastan/larastan)
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)
- [PHPStan Rule Levels](https://phpstan.org/user-guide/rule-levels)
