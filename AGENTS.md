# AGENTS.md

## Project Structure and Verification

- Requires PHP 8.5 or later. Place implementation code in `src/` and tests in `tests/`.
- Use PHPUnit for behavior verification, PHPStan for static analysis, and Mago for linting and formatting.
- `composer test`: Run tests.
- `composer analyse`: Run static analysis.
- `composer lint`: Run lint checks.
- `composer format:check`: Check formatting.
- `composer check`: Run all checks above.

## Static Analysis Suppressions in Tests

- Call the public API directly when testing inputs that intentionally violate static type contracts, such as runtime handling of contract violations.
- Do not use Reflection or dynamic invocation solely to bypass static analysis.
- Make necessary suppressions explicit with a reasoned `@phpstan-ignore` comment scoped to the affected line. Specify only the error identifiers actually reported.
- Explain the runtime behavior that requires intentionally violating the type contract.
- Do not introduce inaccurate type declarations or broad suppressions in analysis configuration or baselines to bypass errors. Fix the underlying cause of ordinary type errors.
- Reflection is allowed when needed to inspect the subject of the test, such as the number of required parameters of a public method.

```php
// @phpstan-ignore argument.type (Non-boolean results intentionally exercise runtime truthiness.)
$sequence->filter(static fn(mixed $value): mixed => $value);
```
