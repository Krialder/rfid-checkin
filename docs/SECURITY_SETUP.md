# Security Configuration Guide

## Protected Files

This project uses `.gitignore` to protect sensitive configuration files from being committed to the repository. The following files contain sensitive information and are not tracked by Git:

### Configuration Files
- `core/config.php` - Database credentials and application settings
- `hardware/config.json` - Hardware configuration with WiFi credentials
- `database/create_admin.sql` - Admin user creation with password hash

### Template Files (Safe for Git)
- `core/config.template.php` - Template for database configuration
- `hardware/config.template.json` - Template for hardware configuration  
- `database/create_admin.template.sql` - Template for admin user creation

## Setup Instructions

### 1. Database Configuration
```bash
# Copy the template and configure your database
cp core/config.template.php core/config.php
```
Then edit `core/config.php` with your actual:
- Database host, name, username, password
- Application URL
- Timezone settings

### 2. Hardware Configuration
```bash
# Copy the template and configure your hardware
cp hardware/config.template.json hardware/config.json
```
Then edit `hardware/config.json` with your actual:
- WiFi SSID and password
- Server URL
- Device-specific settings

### 3. Admin User Setup
```bash
# Copy the template and configure admin user
cp database/create_admin.template.sql database/create_admin.sql
```
Then edit `database/create_admin.sql` with:
- Secure admin email
- Proper password hash (use PHP's `password_hash()` function)

## Security Best Practices

1. **Never commit actual configuration files** - Always use the template files
2. **Use strong passwords** - Generate secure password hashes
3. **Update default credentials** - Change all default passwords immediately
4. **Regular backups** - Keep backups of your configuration files locally
5. **Environment-specific configs** - Use different configurations for development/production

## File Structure
```
├── core/
│   ├── config.php              (ignored by Git)
│   ├── config.template.php     (tracked by Git)
├── hardware/
│   ├── config.json            (ignored by Git)
│   ├── config.template.json   (tracked by Git)
├── database/
│   ├── create_admin.sql       (ignored by Git)
│   ├── create_admin.template.sql (tracked by Git)
└── .gitignore                 (comprehensive protection)
```

## Important Notes

- The actual configuration files (`config.php`, `config.json`, `create_admin.sql`) are already present in your local environment but won't be pushed to GitHub
- Always use the template files as a reference when setting up new environments
- Keep your local configuration files secure and backed up privately
