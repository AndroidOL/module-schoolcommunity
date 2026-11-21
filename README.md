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

- Create categories, select which role groups may use them and control whether
  each category supports group messages.
- Send HTML-formatted messages to active Gibbon users.
- Choose a direct recipient in a searchable modal filtered by the selected
  category's allowed role groups.
- Send a message to yourself without creating duplicate records.
- Switch the normal send form to group mode and choose an existing School
  Community group, an official Gibbon group, an administrative class or a
  course class.
- List sent, received and unread messages with previews, category and priority
  filters, and standard Open/Archive/Delete actions.
- Search direct message replies and group names, messages and replies.
- Archive, trash and restore direct or group threads per user, mark messages
  unread and auto-restore archived/trashed threads when new activity arrives.
- Record a read receipt when the recipient opens a message.
- Reply to a message with participant-only access.
- Create category-controlled groups with owner-managed members.
- Create one linked group per official Gibbon group, administrative class or
  course class; senders can select only groups and classes they own or belong
  to.
- Synchronize linked class membership from the current Gibbon roster.
- Review every resolved recipient before sending, with an explicit block when
  no eligible recipient remains, and freeze a send-time snapshot.
- Send group messages and replies with unread counts, read receipts and
  notifications; later members cannot see earlier messages.
- Notify recipients about new messages and replies.
- Restrict direct message visibility to the sender and recipient.
- Restrict group visibility and all group writes to current members whose role
  is still allowed by the group category.

## Security

- All write forms use Gibbon CSRF tokens and accept POST only.
- Send workflows require both Send Message and View Messages access, so a
  successful write always has an authorised confirmation page.
- Message bodies are sanitized against Gibbon's configured HTML allow-list
  before storage and again before display.
- Category permissions use a four-bit audience mask for Staff, Student, Parent
  and Other roles.
- Group member changes and role changes are checked again on every page load
  and write request.
- Group submissions compare the reviewed recipient signature with a fresh
  server-side resolution before storage.
- Categories cannot be deleted while messages still use them.

## Development

Run the pure helper tests from the Gibbon repository root:

```sh
vendor/bin/phpunit --bootstrap vendor/autoload.php \
  "modules/School Community/tests/ModuleFunctionsTest.php"
```

## License

GPL-3.0
