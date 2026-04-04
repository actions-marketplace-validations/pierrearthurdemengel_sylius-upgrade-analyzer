# Custom Rules Reference

Custom rules allow you to extend the analyzer with project-specific checks without writing PHP code. Rules are defined in a `.sylius-upgrade-rules.yaml` file at the root of the analyzed project.

---

## File Format

```yaml
rules:
  - name: <unique_name>
    type: <rule_type>
    pattern: <search_pattern>
    severity: <severity_level>
    category: <category>
    message: <issue_message>
    suggestion: <fix_suggestion>
    estimated_minutes: <integer>  # optional, defaults to 0
```

---

## Rule Properties

### `name` (required)

A unique identifier for the rule. Used in reports to identify which custom rule triggered an issue.

```yaml
name: legacy_payment_service
```

### `type` (required)

The type of pattern matching to apply. Determines which files are scanned and how the pattern is matched.

| Type | Scans | How it matches |
|------|-------|----------------|
| `php_class_usage` | `*.php` files in `src/` | Searches for fully-qualified class name references |
| `php_method_call` | `*.php` files in `src/` | Searches for method call patterns (string match) |
| `twig_function` | `*.twig` files in `templates/` | Searches for Twig function/filter usage |
| `yaml_key` | `*.yaml` and `*.yml` files in `config/` | Searches for YAML configuration keys |

### `pattern` (required)

The search pattern. This is a plain string match (not a regex). The analyzer searches for this exact string in the relevant files.

```yaml
# Matches any file containing the string "App\Service\LegacyPaymentService"
pattern: 'App\Service\LegacyPaymentService'

# Matches any file containing "->sendOrderConfirmation("
pattern: '->sendOrderConfirmation('

# Matches any Twig file containing "sylius_price_format"
pattern: 'sylius_price_format'

# Matches any YAML file containing "sylius_mailer:"
pattern: 'sylius_mailer:'
```

### `severity` (required)

The severity level of issues created by this rule.

| Value | Description |
|-------|-------------|
| `breaking` | Breaking change requiring mandatory intervention |
| `warning` | Potential issue requiring attention |
| `suggestion` | Improvement suggestion, non-blocking |

### `category` (required)

The category for grouping issues in reports.

| Value | Description |
|-------|-------------|
| `twig` | Templates and Twig hooks |
| `deprecation` | PHP code deprecations |
| `plugin` | Plugin compatibility |
| `grid` | Grid configuration |
| `resource` | Resource bundle configuration |
| `frontend` | Assets and frontend integration |
| `api` | API configuration and endpoints |

### `message` (required)

The primary message displayed in the report when this rule matches. Should clearly describe what was found.

```yaml
message: 'Legacy payment service must be replaced before upgrading to Sylius 2.x'
```

### `suggestion` (required)

Actionable advice on how to fix the detected issue.

```yaml
suggestion: 'Migrate to the new PaymentProcessor service. See internal docs for the migration guide.'
```

### `estimated_minutes` (optional)

Estimated time in minutes to fix each occurrence. Defaults to `0` if omitted.

```yaml
estimated_minutes: 120
```

---

## Complete Examples

### Detecting a deprecated PHP class

```yaml
rules:
  - name: legacy_payment_service
    type: php_class_usage
    pattern: 'App\Service\LegacyPaymentService'
    severity: breaking
    category: deprecation
    message: 'Legacy payment service detected -- must be replaced'
    suggestion: 'Migrate to App\Payment\PaymentProcessor. See UPGRADE.md for details.'
    estimated_minutes: 240
```

### Detecting a deprecated method call

```yaml
rules:
  - name: deprecated_order_method
    type: php_method_call
    pattern: '->getOrderTotal()'
    severity: warning
    category: deprecation
    message: 'Deprecated getOrderTotal() method call detected'
    suggestion: 'Replace with ->getTotal() which returns a Money object.'
    estimated_minutes: 15
```

### Detecting a deprecated Twig filter

```yaml
rules:
  - name: old_price_filter
    type: twig_function
    pattern: 'sylius_price_format'
    severity: warning
    category: twig
    message: 'Deprecated sylius_price_format Twig filter detected'
    suggestion: 'Replace with the money_format filter from Sylius 2.x.'
    estimated_minutes: 10
```

### Detecting a deprecated YAML configuration key

```yaml
rules:
  - name: old_mailer_config
    type: yaml_key
    pattern: 'sylius_mailer:'
    severity: breaking
    category: deprecation
    message: 'Deprecated sylius_mailer configuration detected'
    suggestion: 'Migrate to framework.mailer configuration with Symfony Mailer DSN.'
    estimated_minutes: 60
```

### Detecting custom Twig functions

```yaml
rules:
  - name: custom_channel_helper
    type: twig_function
    pattern: 'app_channel_currency'
    severity: warning
    category: frontend
    message: 'Custom channel currency helper may conflict with Sylius 2.x'
    suggestion: 'Review the helper for compatibility with the new channel scoping.'
    estimated_minutes: 30
```

---

## Full Example File

```yaml
# .sylius-upgrade-rules.yaml
# Custom migration rules for our Sylius project

rules:
  # --- PHP class deprecations ---
  - name: legacy_payment_service
    type: php_class_usage
    pattern: 'App\Service\LegacyPaymentService'
    severity: breaking
    category: deprecation
    message: 'Legacy payment service must be replaced'
    suggestion: 'Migrate to App\Payment\PaymentProcessor'
    estimated_minutes: 240

  - name: old_cart_manager
    type: php_class_usage
    pattern: 'App\Cart\CartManager'
    severity: warning
    category: deprecation
    message: 'CartManager uses deprecated Sylius APIs internally'
    suggestion: 'Refactor to use the new OrderModifier service'
    estimated_minutes: 180

  # --- Method call deprecations ---
  - name: deprecated_send_email
    type: php_method_call
    pattern: '->sendOrderConfirmation('
    severity: warning
    category: deprecation
    message: 'Direct email sending method is deprecated'
    suggestion: 'Use the Symfony Mailer event-based approach instead'
    estimated_minutes: 60

  # --- Twig deprecations ---
  - name: old_price_filter
    type: twig_function
    pattern: 'sylius_price_format'
    severity: warning
    category: twig
    message: 'Deprecated Twig filter detected'
    suggestion: 'Replace with money_format filter'
    estimated_minutes: 10

  - name: semantic_ui_modal
    type: twig_function
    pattern: 'ui modal'
    severity: breaking
    category: frontend
    message: 'Semantic UI modal component detected in template'
    suggestion: 'Replace with the new modal component from Sylius 2.x UI kit'
    estimated_minutes: 45

  # --- YAML configuration deprecations ---
  - name: old_mailer_config
    type: yaml_key
    pattern: 'sylius_mailer:'
    severity: breaking
    category: deprecation
    message: 'Deprecated sylius_mailer configuration block'
    suggestion: 'Migrate to framework.mailer with Symfony Mailer DSN'
    estimated_minutes: 60

  - name: old_shipping_config
    type: yaml_key
    pattern: 'sylius_shipping.calculators:'
    severity: warning
    category: deprecation
    message: 'Deprecated shipping calculator configuration syntax'
    suggestion: 'Update to the new calculator registration format'
    estimated_minutes: 30
```

---

## How Custom Rules Are Loaded

1. The `CustomRuleLoader` service looks for `.sylius-upgrade-rules.yaml` at the root of the analyzed project.
2. It validates each rule against the schema (required fields, valid types/severities/categories).
3. The `CustomRuleAnalyzer` iterates through loaded rules and scans the appropriate files.
4. For each match, a `MigrationIssue` is created with the rule's severity, category, message, and estimation.

### Using a Custom Path

You can specify a different rules file with the `--rules` option:

```bash
vendor/bin/sylius-upgrade-analyzer sylius-upgrade:analyze --rules=/path/to/my-rules.yaml
```

---

## Validation

The loader validates every rule strictly. If a rule is malformed, an `InvalidArgumentException` is thrown with a clear error message. Common validation errors:

- Missing required field (`name`, `pattern`, `type`, `severity`, `category`, `message`, `suggestion`)
- Invalid `type` (must be one of: `php_class_usage`, `php_method_call`, `twig_function`, `yaml_key`)
- Invalid `severity` (must be one of: `breaking`, `warning`, `suggestion`)
- Invalid `category` (must be one of: `twig`, `deprecation`, `plugin`, `grid`, `resource`, `frontend`, `api`)
- Invalid `estimated_minutes` (must be a positive integer)
