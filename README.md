# FSA - Fastly Streamline Access

This module provides a simple way of adding IP addresses to Fastly ACLs.

The mechanism is simple - a user logs in, or visits, `/user`, and the module will attempt to add their IP address to the ACL targeted in `fastly_streamline_access.settings`.

## Installation

TBD

## Setup

Visit `/admin/config/development/fastly_streamline_access` to set up the module.

### Prerequisites

This module expects the environment variable **FSA_KEY** to exist.
**FSA_KEY** will contain an encrypted version of the Fastly Service Key, which is decrypted using the passphrase set in the FSA administration page.

This is an admittedly imperfect measure to avoid putting the Fastly Key directly into the env variables themselves - it is simply a way of effectively obscuring the key in the case it is printed to logs etc.

### Settings

On the administration page you will find the following settings

- **Standard Access Control List (ACL) name** - this is where the present module will record all IP addresses. The value of this setting should correspond to the ACL name.
- **Long lived Access Control List (ACL) name:** - this is where the admin module will record all IP addresses manually tagged for longer TTLs that average. The value of this setting should correspond to the ACL name.
- **Api Pass phrase** - The pass phrase used in decrypting the service key. Once set, you will need to explicitly check the "Override Passphrase" option to overwrite it.

### Permissions

This module makes the permission `Access protected Lagoon routes` available.
Any user assigned this permission via roles will have their IP address recorded in the ACL when logging into the site.

