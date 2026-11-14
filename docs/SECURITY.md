# PawHaven — Security Architecture & Guidelines

PawHaven is engineered for self-hosted commercial deployments, adhering to defense-in-depth principles.

---

## 1. Authentication & Identity Protection

### Password Hashing
- Utilizes the **Bcrypt** algorithm configured with 12 calculation rounds (`BCRYPT_ROUNDS=12`).
- Passwords must be at least 8 characters long and are never stored or logged in plain text.

### Two-Factor Authentication (2FA) & Passkeys
- Integrated via Laravel Fortify:
  - Time-based One-Time Passwords (TOTP via Google Authenticator, Authy, 1Password).
  - Encrypted recovery codes generated upon 2FA setup.
  - Password re-confirmation required before accessing sensitive security settings (`password.confirm` middleware).
  - Native WebAuthn / Passkey support (`PasskeyAuthenticatable`).

### Session Security
- Default session driver is set to `database`, preventing file-based session hijacking in shared hosting environments.
- Cookies utilize `HttpOnly`, `SameSite=Lax`, and `Secure` attributes over HTTPS.

---

## 2. Insecure Direct Object Reference (IDOR) & Role Isolation

PawHaven enforces authorization checks at the controller and route layer using dedicated Laravel Policies:

| Policy | Protected Entity | Enforcement Rules |
| :--- | :--- | :--- |
| **`PetListingPolicy`** | `PetListing` | - Public visitors can only view `Published` listings.<br>- Sellers can only view, edit, pause, and delete listings where `seller_id === auth()->id()`.<br>- Administrators have moderation privileges. |
| **`OrderPolicy`** | `Order` | - Customers can only view their own orders (`user_id === auth()->id()`).<br>- Customers can only cancel orders in `Pending` status.<br>- Sellers can only view and update requests assigned to their shop (`seller_id === auth()->id()`).<br>- Administrators can view all orders. |
| **`ProductPolicy`** | `Product` | - Modifying shop inventory is strictly restricted to `admin` and `staff` roles. |

> [!IMPORTANT]
> PawHaven does not rely on hidden UI buttons for security. Every request mutation validates user authorization server-side using `Gate::authorize()`. Manipulated URL parameters or forged form submissions result in HTTP `403 Forbidden`.

---

## 3. Input Validation & Mass-Assignment Protection

- Every model explicitly defines allowed mass-assignment properties via Eloquent `#[Fillable([...])]`.
- Controller inputs are sanitized and strictly validated with Form Request or inline validator arrays.
- Monetary transactions are represented exclusively in integer minor units (e.g. cents/pence) to eliminate floating-point precision vulnerabilities.

---

## 4. File Upload Security

Pet photos and brand logos are handled via strict validation pipelines:
- **Allowed MIME types**: `image/jpeg`, `image/png`, `image/webp`. Executable files (`.php`, `.phtml`, `.sh`, `.exe`) are rejected before disk write.
- **Maximum file size**: 2048 KB (2MB) per image, with a limit of 5 images per listing.
- **Storage Isolation**: Stored with randomized hash filenames in `storage/app/public/listings/` and `storage/app/public/branding/`. Stored files are served without server-side script execution.

---

## 5. Network & Privacy Guarantees

- **No Remote Telemetry**: The codebase contains zero external tracking pixels, analytics beacons, or license ping-backs.
- **No Developer Callbacks**: The application operates autonomously without requiring access to external developer infrastructure.
- **Environment Isolation**: `.env`, SQLite databases, private keys, and application logs are excluded from version control by default.
