# SSO: installation and update guide

Server: Windows, Apache (for example XAMPP), PHP 8.4, SQL Server.

This guide covers everything needed to install the SSO on a production server, keep it
running, and install new versions. Follow the steps in order. Each step ends with a
**Check**: do not continue until the check passes.

The examples use **https://sso.example.com** as the address and `C:\xampp` as the XAMPP
folder: replace them with your own.

---

## 0. How it works, in short

- **The server needs no internet, Composer, Node.js or PowerShell.** It only needs Apache,
  PHP and the cmd window. Everything else comes inside one **release zip**.
- Download the release zip from the project's
  [GitHub Releases](https://github.com/tahir242/sso/releases) page, or build it yourself
  (section 2), and copy it to the server.
- On the server, three ready-made scripts do the work:

| Script (in `C:\xampp\htdocs\sso\deploy\`) | When | What it does |
|---|---|---|
| `install.cmd` | Once, first installation | Creates `.env`, the keys and the database tables |
| `update.cmd <zip>` | Every new version | Replaces the code, keeps settings and keys, updates the database |
| `scheduled-task.cmd` | Once, by IT as administrator | Creates the Windows task that runs background jobs every minute |

**Where things are on the server**

| What | Where |
|---|---|
| The SSO | `C:\xampp\htdocs\sso` |
| Settings file (passwords, database) | `C:\xampp\htdocs\sso\.env` |
| Token signing keys | `C:\xampp\htdocs\sso\storage\oauth-private.key` and `oauth-public.key` |
| Uploaded files (logo, attachments) | `C:\xampp\htdocs\sso\storage\app\` |
| Error logs (one file per day) | `C:\xampp\htdocs\sso\storage\logs\` |
| PHP | `C:\xampp\php84\php.exe` |

In the commands below, `php` means `C:\xampp\php84\php.exe`. Run them from the SSO folder:

```
cd /d C:\xampp\htdocs\sso
```

> **Keep these four things in the IT password safe** (they cannot be recreated):
> `.env`, `storage\oauth-private.key`, `storage\oauth-public.key`, and the backup password.

---

## 1. One-time server preparation

### 1.1 PHP (`C:\xampp\php84\php.ini`, the PHP that Apache uses)

Make sure these lines are present and not commented out (no `;` in front):

```ini
extension=php_sqlsrv_84_nts_x64.dll
extension=php_pdo_sqlsrv_84_nts_x64.dll
extension=openssl
extension=curl
extension=mbstring
extension=zip
extension=fileinfo
extension=ldap
extension=sodium

zend_extension=opcache
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
realpath_cache_size=4096K
expose_php=Off
```

- The two `sqlsrv` DLL files must be in `C:\xampp\php84\ext\` (Microsoft Drivers for PHP for SQL Server), and the **Microsoft ODBC Driver 17 (or 18) for SQL Server** must be installed on the server.
- `opcache.validate_timestamps=0` makes the site fast but means **Apache must be restarted after every update** (the update steps include this).

**Check:** in cmd, `C:\xampp\php84\php.exe -m` lists `pdo_sqlsrv`, `ldap`, `sodium` and `Zend OPcache`.

### 1.2 Apache

In `httpd.conf`: `mod_rewrite` and `mod_ssl` enabled, and

```apache
ServerTokens Prod
ServerSignature Off

<VirtualHost *:443>
    ServerName sso.example.com
    DocumentRoot "C:/xampp/htdocs/sso/public"
    SSLEngine on
    SSLCertificateFile    "conf/ssl.crt/sso.example.com.crt"
    SSLCertificateKeyFile "conf/ssl.key/sso.example.com.key"
    <Directory "C:/xampp/htdocs/sso/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost *:80>
    ServerName sso.example.com
    Redirect permanent / https://sso.example.com/
</VirtualHost>
```

- The document root is the **`public`** folder, never the SSO folder itself: `.env`, `storage` and the keys must not be reachable from the browser.
- The Windows account Apache runs as needs **write** access to `storage\` and `bootstrap\cache\` (read access is enough everywhere else).

#### PHP through `mod_fcgid` (several PHP versions on one Apache)

When Apache runs PHP 8.4 through `mod_fcgid` (common when other sites on the server need another
PHP version), the number of PHP processes decides how many requests are handled at once. XAMPP's
default of 5 for the **whole server** makes every site queue behind the others.

Server-wide, in `httpd.conf`:

```apache
<IfModule fcgid_module>
    FcgidIOTimeout 64
    FcgidConnectTimeout 16
    FcgidMaxRequestsPerProcess 1000
    # All PHP processes on the server, for every site together.
    FcgidMaxProcesses 24
    FcgidMaxRequestLen 8131072
</IfModule>
```

And in the SSO's `<VirtualHost>`, in place of the `<Directory>` block above:

```apache
    FcgidInitialEnv PHPRC "C:/xampp/php84"
    # PHP processes for the SSO alone (it may use up to this many of FcgidMaxProcesses).
    FcgidMaxProcessesPerClass 16
    # Keep this many SSO processes running when the site is quiet (no slow first requests).
    FcgidMinProcessesPerClass 4
    <Directory "C:/xampp/htdocs/sso/public">
        <FilesMatch "\.php$">
            SetHandler fcgid-script
            FcgidWrapper "C:/xampp/php84/php-cgi.exe" .php
        </FilesMatch>
        Options +ExecCGI -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
```

- `FcgidMaxProcessesPerClass` (SSO) must be lower than `FcgidMaxProcesses` (whole server), so the
  other sites keep some processes.
- Each PHP process uses about 40–60 MB of memory: 24 processes is about 1–1.5 GB at the busiest
  moment. Raise both values on a server with more memory and CPU cores.
- Apache stops PHP processes that have been idle for 5 minutes (`FcgidIdleTimeout`). Starting
  new ones makes the first requests after a quiet period take 1–2 seconds instead of under half
  a second. `FcgidMinProcessesPerClass 4` keeps at least 4 SSO processes running once they have
  started, at a cost of about 200 MB of memory.
- `FcgidInitialEnv PHPRC` makes sure these processes read `C:\xampp\php84\php.ini`, where OPcache
  is turned on (1.1).
- Restart Apache after changing any of this, then run the speed test (3.8).

Measured on the test server (`/login`, 20 people at once): with OPcache off and
`FcgidMaxProcesses 5`, about 4 requests per second with some people waiting up to 47 seconds;
with OPcache on and `FcgidMaxProcesses 24`, about 45 requests per second with 95% of requests
under 0.5 seconds.

### 1.3 SQL Server database (DBA)

The DBA creates an empty database and a SQL login that owns it. Run in SQL Server Management Studio:

```sql
CREATE DATABASE SSO;
GO
-- skip the next line if the login already exists
CREATE LOGIN SSO_APP WITH PASSWORD = '<strong password>', CHECK_POLICY = ON;
GO
USE SSO;
GO
CREATE USER SSO_APP FOR LOGIN SSO_APP;
ALTER ROLE db_owner ADD MEMBER SSO_APP;
GO
```

- Use a dedicated login like `SSO_APP`, never `sa`.
- The database must exist **before** installing. If it doesn't, the install fails with
  `Login failed for user 'SSO_APP'` even when the password is right.
- The DBA's normal backups (full nightly + log) apply to `SSO` like any other system.
- **Version:** SQL Server 2017 or later is recommended. SQL Server 2012 and 2014 also work (the
  one newer SQL feature Laravel uses is replaced), but `sso:check` shows a ✘ for the version,
  because they no longer get security updates.

**Check:** the DBA confirms `SSO_APP` can sign in with `SSO` as its database.

---

## 2. Get the release zip

**Easiest:** download `sso-vX.Y.Z.zip` from
[GitHub Releases](https://github.com/tahir242/sso/releases). Every release is built and tested
automatically.

**Or build it yourself** on a Windows machine with internet, PHP 8.4, Composer, Node.js 20+
and Git. In PowerShell:

```
git clone https://github.com/tahir242/sso.git
cd sso
.\deploy\build-release.ps1 -Version v1.0.0
```

It creates **`..\sso-v1.0.0.zip`** (about 30 MB), next to the project folder. That one file is
what goes to the server. It contains the program, all packages, the compiled design files and
the `deploy` scripts. It does **not** contain `.env`, keys, uploads, logs or tests.

Copy the zip to the server, for example to `D:\Releases\`.

**Check:** the zip exists and is about 30 MB.

---

## 3. First installation (production server)

### 3.1 Unpack

In cmd:

```
mkdir C:\xampp\htdocs\sso
tar -xf D:\Releases\sso-vX.Y.Z.zip -C C:\xampp\htdocs\sso
```

### 3.2 Create the settings file

```
C:\xampp\htdocs\sso\deploy\install.cmd
```

The first run only creates `.env` and stops. Open it with Notepad:

```
notepad C:\xampp\htdocs\sso\.env
```

Fill in:

| Setting | Value |
|---|---|
| `APP_URL` | `https://sso.example.com` |
| `APP_KEY` | leave empty: the script fills it in |
| `DB_HOST` | the SQL Server, e.g. `"DBSERVER\\INSTANCE"` (in quotes, with two backslashes) or `db.example.com` |
| `DB_DATABASE` | `SSO` |
| `DB_USERNAME` | `SSO_APP` |
| `DB_PASSWORD` | the login's password, in quotes |
| `DB_TRUST_SERVER_CERTIFICATE` | `false` if the SQL Server has a proper certificate, otherwise `true` |
| `SSO_BACKUP_PATH` | folder for the nightly backups, ideally another disk or a share, e.g. `\\backup-server\sso` (empty = `storage\app\backups`) |
| `SSO_BACKUP_PASSWORD` | a new password of 16+ characters; put it in the password safe |
| `SSO_BACKUP_SQLSERVER_PATH` | folder **on the SQL Server machine** for a database copy, e.g. `D:\Backups\SSO`; empty if the DBAs back up the database |

Leave everything else as it is. Mail, Active Directory, WhatsApp and two-step verification are
**not** set here but in the admin console (step 3.6).

### 3.3 Install

Run the same script again:

```
C:\xampp\htdocs\sso\deploy\install.cmd
```

It checks PHP, creates the application key and the token signing keys, creates all database
tables, loads the starting data (roles, quotes, occasions, helpdesk categories) and ends with a
readiness report.

**Check:** the script ends with "Next:" and no error. In the report, ✘ for "No super-admin"
and for the scheduler and queue is normal at this point (fixed in the next steps).

### 3.4 Create the first administrator

```
cd /d C:\xampp\htdocs\sso
C:\xampp\php84\php.exe artisan sso:create-admin
```

Answer the four questions:

| Question | Example |
|---|---|
| Username | `sso.admin` |
| Full name | `SSO Administrator` |
| Email | `it@example.com` |
| Password | at least 12 characters; nothing appears while typing |

- Use a name that is not a staff member's Windows username (those may already exist).
- This is a local emergency account: keep its password in the password safe.
- To give your own Windows account admin rights, sign in as `sso.admin`, open
  **Admin console → Identity → Users → your account**, and add the role **super-admin**.

### 3.5 Background jobs (IT, once)

IT opens **cmd as administrator** and runs:

```
C:\xampp\htdocs\sso\deploy\scheduled-task.cmd
```

This creates the Windows task **"SSO scheduler"**, which runs every minute. It sends
emails, WhatsApp and push messages, signs users out of applications when they sign out of the
SSO, escalates waiting approvals, runs access reviews and helpdesk deadlines, and makes the
nightly backup. Nothing else (no service or worker) is needed.

- It runs as `SYSTEM`. If Apache runs as a different account, pass that account instead
  (`scheduled-task.cmd DOMAIN\account`; it asks for the password) so both can write to `storage\`.
- To create it by hand in Task Scheduler instead: Create Task → "Run whether user is logged on or
  not" and "Run with highest privileges" → Trigger: Daily, repeat every 1 minute, indefinitely →
  Action: program `C:\xampp\php84\php.exe`, arguments `C:\xampp\htdocs\sso\artisan schedule:run`
  → Settings: "Do not start a new instance" if already running.

### 3.6 Start and configure

1. Restart Apache.
2. Open **https://sso.example.com/login** and sign in as `sso.admin`. Under **Account → Security**, turn on
   two-step verification with an authenticator app (recommended; each user decides for themselves).
3. Open **Admin console** (top right) → **Settings** and fill in:

| Settings page | What to set |
|---|---|
| General | Organisation name, logo |
| Mail | SMTP server, sender address; use "Send test email" |
| Active Directory | Domain controller, base DN, bind account; use "Test saved connection" |
| Notifications | WhatsApp (Green-API) details, which messages go by which channel; use "Send test WhatsApp" |
| Two-step verification | How long a computer is remembered, name shown in authenticator apps |
| Sign-in security | Lockout limits, password rules |
| Sessions | How long people stay signed in |
| Access requests, Helpdesk | Approval and helpdesk rules |

4. Register the applications under **Identity → Applications** (each gets a client ID and
   secret). How applications connect is explained at **https://sso.example.com/docs/integration**
   (visible to administrators).

### 3.7 Final check

```
C:\xampp\php84\php.exe artisan sso:check --http
```

**Check:** no ✘ (on SQL Server 2012 or 2014 the version shows ✘, see 1.3). Every ⚠ warning is understood and
accepted. "Scheduler ran" and "Queue" show ✔ (wait 2 to 15 minutes after step 3.5).

Then copy **`.env`, `storage\oauth-private.key` and `storage\oauth-public.key`** to the IT
password safe.

### 3.8 Optional: speed test

From any machine with PowerShell and a copy of the repository, against the live server:

```
.\tests\load\run-load-test.ps1 -BaseUrl https://sso.example.com
```

Target with 20 people at once: 95% of requests under 0.5 seconds and no errors. If it is much
slower, check that OPcache is on (1.1) and that Apache allows enough PHP processes (1.2).

---

## 4. Installing a new version

1. **Download or build** the new zip (section 2), read its release notes (`CHANGELOG.md`), and
   copy it to the server.
2. **Back up** first, on the server:
   ```
   cd /d C:\xampp\htdocs\sso
   C:\xampp\php84\php.exe artisan sso:backup
   ```
   Keep the previous release zip as well.
3. **Update:**
   ```
   C:\xampp\htdocs\sso\deploy\update.cmd D:\Releases\sso-vX.Y.Z.zip
   ```
   Answer `Y`. The site shows a maintenance page for a minute or two while the script replaces
   the code, applies database changes and rebuilds its caches. `.env`, the keys, uploads and logs
   are never touched.
4. **Restart Apache** (required because of OPcache).
5. **Check:** `C:\xampp\php84\php.exe artisan sso:check --http`, then sign in once.

**If the update script stops with an error**, the site stays on the maintenance page (on
purpose). Read the message; the details are in `storage\logs\`. After fixing the cause, run the
same `update.cmd` command again.

**To go back to the previous version:** ask the DBA to restore the database from before the
update (or the copy made by `sso:backup` in `SSO_BACKUP_SQLSERVER_PATH`), then run
`update.cmd` with the **previous** zip and restart Apache.

---

## 5. Running it day to day

### Health check

```
C:\xampp\php84\php.exe artisan sso:check
```

Shows in one table whether the database, keys, background jobs, mail and Active Directory are
working. Run it after any change and whenever something seems wrong.

### Changing `.env`

The settings are cached for speed. After editing `.env`, run:

```
C:\xampp\php84\php.exe artisan optimize
```

and restart Apache. Without this, the change has no effect.

### Backups

- Every night at 01:30 the SSO makes an encrypted backup (`.zip`, AES-256, the backup password)
  of the keys, `.env` and uploaded files into `SSO_BACKUP_PATH`, plus a checked database copy
  into `SSO_BACKUP_SQLSERVER_PATH` when that is set. The last 14 are kept.
- Make one by hand: `php artisan sso:backup`.
- Check one: `php artisan sso:backup --verify=<path to the zip>`; it should say
  "complete and readable".

### Restore drill (before go-live, then every 6 months)

1. `php artisan sso:backup`, then `php artisan sso:backup --verify=<archive>`.
2. On a spare machine: restore the database copy into a new database; open the archive with 7-Zip
   and the backup password; put `keys\*` into `storage\`, `env\.env` into the SSO folder (point
   `DB_*` at the restored database), and `files\*` into `storage\app\private\`.
3. Run `php artisan sso:check`, then sign in to an application through the restored SSO. If the
   keys were restored, people stay signed in to their applications.
4. Write down how long it took. Target: under 2 hours.

**What happens if something is lost:** without `oauth-private.key` everyone is signed out of
every application once (new keys are created; not a disaster). Without `APP_KEY` the saved
passwords for mail, Active Directory and WhatsApp can't be read and must be entered again in
Settings.

### Things to look at regularly

- **Admin console → Audit → Sign-in activity:** failed sign-ins and lockouts.
- **Failed background jobs:** `php artisan queue:failed` lists them; after fixing the cause,
  `php artisan queue:retry all` sends them again.
- **Logs:** `storage\logs\laravel-<date>.log` (kept 30 days).
- **Access reviews:** schedule them in the admin console (Audit → Access reviews).

### Automatic clean-up

Every night the scheduler deletes what the SSO no longer needs, so the database stays small
however many people use it (in batches, so nothing is locked for long):

| What | Kept for |
|---|---|
| Expired and revoked tokens and sign-in codes (`passport:purge`, 01:15) | 7 days after they expire |
| Application sessions (`sso:prune`, 01:00) | 30 days after sign-out |
| Sign-in events (Audit → Sign-in activity) | 1 year |
| Change history (who changed what in the admin console) | 1 year |
| Read notifications | 90 days (unread ones are kept) |
| Remembered computers | until they expire |
| Failed background jobs | 30 days |

To keep audit records longer (for example for an inspection), change the numbers in
`app\Support\Operations\Retention.php` before the next night, or take a database backup first.
Run it by hand under Admin console → Maintenance commands → Prune old records.

---

## 6. Troubleshooting

| Problem | Cause and fix |
|---|---|
| `Login failed for user 'SSO_APP'` | The database in `DB_DATABASE` doesn't exist, or the login has no rights on it, or the password is wrong. The DBA checks section 1.3. |
| `could not find driver` | PHP's `pdo_sqlsrv` extension is not loaded (1.1). |
| "500 / Server Error" page | Read the newest file in `storage\logs\`. |
| Site stuck on the maintenance page | An update stopped halfway: fix the cause and run `update.cmd` again. Only if you are sure the code is complete: `php artisan up`. |
| Change in `.env` has no effect | Run `php artisan optimize` and restart Apache. |
| Changed code has no effect | Restart Apache (OPcache). |
| No emails or WhatsApp messages | `sso:check`: is the scheduler running? Is mail set up in Settings? See `php artisan queue:failed`. |
| "Scheduler not running" in `sso:check` | The scheduled task is missing or disabled: Task Scheduler → "SSO scheduler" → Last Run Result. Rerun `scheduled-task.cmd` as administrator. |
| Everyone signed out of the applications | The signing keys in `storage\` were replaced or lost. Restore them from the backup or the password safe. |
| Windows sign-in fails for everyone | Active Directory settings or the bind account password: Admin console → Settings → Active Directory → "Test saved connection". |
| Administrator locked out | Create a new emergency administrator with `php artisan sso:create-admin`. |

---

## 7. Reference

### Commands (run in `C:\xampp\htdocs\sso` as `C:\xampp\php84\php.exe artisan ...`)

| Command | Use |
|---|---|
| `sso:check` | Health / readiness report (`--http` also tests the live site) |
| `sso:create-admin` | Create an administrator account |
| `sso:backup` | Make a backup now (`--verify=<zip>` checks one) |
| `optimize` | Rebuild the caches after changing `.env` |
| `queue:failed` / `queue:retry all` | Show / resend failed background jobs |
| `down` / `up` | Turn the maintenance page on / off |

### Web addresses

| Address | What |
|---|---|
| `https://sso.example.com/login` | Sign in |
| `https://sso.example.com/home` | Staff portal (their applications, requests, account) |
| `https://sso.example.com/admin` | Admin console |
| `https://sso.example.com/docs/integration` | How to connect an application (administrators) |
| `https://sso.example.com/docs/api` | API reference (administrators) |
| `https://sso.example.com/.well-known/openid-configuration` | OpenID Connect details for applications |
