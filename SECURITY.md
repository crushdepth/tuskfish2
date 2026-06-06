# Security Policy

## Supported Versions

Version 2.0 and up are currently supported, while Version 1.x has been deprecated.

| Version | Supported          |
| ------- | ------------------ |
| 2.x     | :white_check_mark: |
| 1.x     | :x:                |

## Reporting a Vulnerability

Please report vulnerabilities by email to simon 'at' isengard.biz. All
such reports are appreciated.

Please allow me four weeks to fix the issue before publicly disclosing it.
I may ask for more time if I need it. Vulnerabilities that are responsibly
disclosed will be publicly credited in patch/commit notes together with
the notification date.

Thanks.

## SMTP password encryption at rest

The SMTP password is encrypted before it is stored in the database, using
libsodium (XSalsa20-Poly1305, authenticated). The encryption key is a random
32-byte value held in `trust_path/configuration/config.php` as the constant
`TFISH_ENCRYPTION_KEY` — never in the database. This protects the stored
password if the database alone is disclosed (for example, a leaked backup of
the database file). It does not protect against compromise of the config file
itself, since the running application must be able to decrypt.

**New installations:** the key is generated automatically during installation.
No action is required.

**If the key is absent** (for example, an older site upgraded in place, or if
you delete the constant), the SMTP password is stored as plain text exactly as
before. Encryption is therefore opt-in for existing sites and fully reversible.

> **Keep the key safe.** Changing or removing `TFISH_ENCRYPTION_KEY` makes any
> already-encrypted password undecryptable. The application fails soft (it reads
> the password as empty rather than erroring); just re-enter the SMTP password
> in the admin preferences and re-save.

### Enabling encryption on an existing site

`config.php` is normally set to read-only (0400), so this is a one-time manual
step:

1. Generate a key:
   `php -r 'echo base64_encode(sodium_crypto_secretbox_keygen()), PHP_EOL;'`
2. Make the config writable: `chmod 0600 trust_path/configuration/config.php`
3. Append this line (using your generated key), then save:
   ```php
   if (!\defined("TFISH_ENCRYPTION_KEY")) define("TFISH_ENCRYPTION_KEY", "<paste-key>");
   ```
4. Restore read-only: `chmod 0400 trust_path/configuration/config.php`
5. In the admin preferences, re-save the SMTP settings once. This re-stores the
   existing password in encrypted form (until then it keeps working as plain
   text). Confirm the stored value now begins with `enc:v1:`.
