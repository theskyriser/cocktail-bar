---
paths:
  - "**/*.php"
---

# MJML Email Guide - Simple & Straightforward

## Quick Start - 3 Steps to Send an Email

### Step 1: Create Your Template

Create a file in `resources/mjml/templates/my-email.mjml`. It uses [MJML](https://documentation.mjml.io/), example:

```mjml
<mj-section background-color="#ffffff" padding="30px 20px">
  <mj-column>
    <mj-text font-size="24px" color="#333333" font-weight="bold">
      Hello {{ $customerName }}!
    </mj-text>

    <mj-text font-size="16px" color="#666666" padding-top="20px">
      {{ $message }}
    </mj-text>

    <mj-button
      background-color="#007bff"
      color="#ffffff"
      href="{{ $actionUrl }}"
    >
      {{ $buttonText }}
    </mj-button>
  </mj-column>
</mj-section>
```

### Step 2: Send the Email

```php
use App\Service\Mail\Factories\MailFactory;
use App\Service\Mail\MailerServiceContract;

$mailerService = app(MailerServiceContract::class);

$mailData = MailFactory::mjmlEmailWithTheme(
    to: ['customer@example.com'],
    templateName: 'my-email',
    subject: 'Welcome!',
    theme: 'light', // or 'dark'
    templateData: [
        'customerName' => 'John Doe',
        'message' => 'Thanks for joining us!',
        'buttonText' => 'Get Started',
        'actionUrl' => 'https://app.example.com/start'
    ]
);

$mailerService->send($mailData);
```

### Step 3: Test It

```php
// In your test file
Mail::fake();

// Send email (code from Step 2)

Mail::assertSent(MjmlMailable::class);
```

Done! Your email will automatically include the default header and footer.

---

## Pre-Built Emails

For common emails, use these shortcuts, example:

### Forgotten Password Email

```php
$mailData = MailFactory::forgottenPasswordEmail(
    to: ['user@example.com'],
    firstName: 'John',
    resetUrl: 'https://app.example.com/reset?token=abc123'
);
```

---

## Themes

Themes swap the shared header/footer components — background colour and the logo variant — so
individual templates never hardcode branding. Two are conventional: `light` (default) and `dark`.

Example:

```php
// Use dark theme
$mailData = MailFactory::mjmlEmailWithTheme(
    // ... your parameters
    theme: 'dark'
);
```

---

## Email Without Header/Footer

For system emails without branding:

```php
$mailData = MailFactory::mjmlEmailWithTheme(
    to: ['admin@example.com'],
    templateName: 'system-alert',
    subject: 'System Alert',
    useDefaultHeader: false,
    useDefaultFooter: false
);
```

---

## Template Variables

In your MJML templates, use:

- `{{ $customerName }}` - Customer's name
- `{{ $message }}` - Your message
- `{{ $actionUrl }}` - Button link
- `{{ $buttonText }}` - Button text
- Any custom variable you pass in `templateData`

---

## File Structure

```
resources/mjml/
├── components/           # System components (don't edit)
│   ├── header-light.mjml
│   ├── header-dark.mjml
│   ├── footer-light.mjml
│   └── footer-dark.mjml
└── templates/           # Your email templates
    └── my-email.mjml    # Your new templates go here
```

---

## Adding to MailFactory (Optional)

If you want to create a reusable email method:

```php
// In app/Service/Mail/Factories/MailFactory.php

public static function myCustomEmail(
    array $to,
    string $customerName,
    string $message,
    string $actionUrl,
    string $theme = 'light'
): SendMailData {
    return self::mjmlEmailWithTheme(
        to: $to,
        templateName: 'my-email',
        subject: 'Custom Email',
        theme: $theme,
        templateData: [
            'customerName' => $customerName,
            'message' => $message,
            'buttonText' => 'Click Here',
            'actionUrl' => $actionUrl,
        ]
    );
}
```

Then use it:

```php
$mailData = MailFactory::myCustomEmail(
    to: ['customer@example.com'],
    customerName: 'John Doe',
    message: 'Your custom message here',
    actionUrl: 'https://app.example.com/action'
);
```

---

## Testing

Always assert with `Mail::fake()` rather than sending for real. Run the targeted mail tests with a
`--filter` on the relevant test class:

```bash
artisan test --filter=MailTest
artisan test --filter=MjmlServiceTest
```

---

## Useful tips

1. **Template not found**: Check file is in `resources/mjml/templates/`
2. **Variables not showing**: Use `{{ $variableName }}` format
3. **MJML errors**: Test syntax at https://mjml.io/try-it-live
4. **Spatie MJML**: https://github.com/spatie/mjml-php

---
