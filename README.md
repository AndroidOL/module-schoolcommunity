# School Community for Gibbon

School Community adds categorised, auditable messages for staff, students and
families to Gibbon. It complements Gibbon Messenger with role-based categories
and a home-school communication workflow.

## Requirements

- Gibbon 31.0.00 or later
- PHP 8.2 or later
- MySQL 8 compatible server

## Installation

1. Copy the `School Community` directory into `modules/`.
2. Sign in as an administrator and open **System Admin → Manage Modules**.
3. Install or update the module.
4. Grant **Manage Message Categories** only to trusted administrators.
5. Grant **View Messages** and **Send Message** to the required role groups.

## Features

- Create categories and select which role groups may use them.
- Send HTML-formatted messages to active Gibbon users.
- List sent, received and unread messages with category and priority filters.
- Record a read receipt when the recipient opens a message.
- Reply to a message with participant-only access.
- Notify recipients about new messages and replies.
- Restrict direct message visibility to the sender and recipient.

## Security

- All write forms use Gibbon CSRF tokens and accept POST only.
- Message bodies are sanitized against Gibbon's configured HTML allow-list
  before storage and again before display.
- Category permissions use a four-bit audience mask for Staff, Student, Parent
  and Other roles.
- Categories cannot be deleted while messages still use them.

## Development

Run the pure helper tests from the Gibbon repository root:

```sh
vendor/bin/phpunit --bootstrap vendor/autoload.php \
  "modules/School Community/tests/ModuleFunctionsTest.php"
```

## License

GPL-3.0
