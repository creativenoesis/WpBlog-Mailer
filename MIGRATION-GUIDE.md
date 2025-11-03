# WP Blog Mailer Free Version - Migration Guide

This guide explains how to move the free version to a separate repository and prepare it for WordPress.org submission.

## What Is This?

The `wpblog-mailer-free/` folder contains a **WordPress.org-ready free version** of WP Blog Mailer with:

✅ All Free tier features
✅ No Freemius licensing code
✅ No premium (Pro/Starter) features
✅ WordPress.org-compliant structure
✅ Tasteful upgrade prompts to your premium version

## Quick Start

### Option 1: Move to New Repository (Recommended)

```bash
# 1. Create a new GitHub repository (e.g., wpblog-mailer-free)

# 2. Navigate to the free version folder
cd wpblog-mailer-free

# 3. Initialize git
git init

# 4. Add your new repository as remote
git remote add origin https://github.com/yourusername/wpblog-mailer-free.git

# 5. Add and commit files
git add .
git commit -m "Initial WordPress.org free version"

# 6. Push to GitHub
git push -u origin main
```

### Option 2: Keep in Same Repository

You can also keep this as a subfolder in your main repository. The build script (`build-free-version.sh`) can regenerate it whenever you update the premium version.

## Files Structure

```
wpblog-mailer-free/
├── wp-blog-mailer.php          # Main plugin file (no Freemius)
├── uninstall.php               # Cleanup on deletion
├── readme.txt                  # WordPress.org readme
├── README.md                   # GitHub readme
├── composer.json               # Dependencies
├── includes/
│   ├── Core/
│   │   ├── Autoloader.php      # Class autoloader
│   │   ├── Plugin.php          # Simplified main class
│   │   └── ServiceContainer.php # DI container (free only)
│   ├── Free/
│   │   ├── Controllers/        # Admin controllers
│   │   ├── Services/           # Free services
│   │   └── Views/              # Admin pages
│   └── Common/
│       ├── Database/           # DB schema & queries
│       ├── Models/             # Data models
│       ├── Services/           # Shared services
│       └── Utilities/
│           ├── helpers.php     # General helpers
│           └── free-helpers.php # Tier stubs
├── assets/
│   ├── css/                    # Stylesheets
│   ├── js/                     # JavaScript
│   └── images/                 # Images
└── templates/                  # Email templates
```

## Key Differences from Premium Version

### Removed Components

❌ **Freemius SDK** - All licensing removed
❌ **Pro/Starter Features** - Tags, segments, advanced analytics
❌ **Email Queue** - Direct sending only
❌ **Import/Export** - Manual subscriber addition only
❌ **Custom Templates** - Basic template only

### Simplified Code

✅ **ServiceContainer.php** - Only loads free services
✅ **Plugin.php** - Simplified menu, no tier checks
✅ **free-helpers.php** - Stub functions return free-tier values

### Added Features

✅ **Upgrade Page** - Dedicated "Upgrade to Pro" menu item
✅ **Upgrade Notices** - Tasteful prompts in admin
✅ **readme.txt** - WordPress.org-compliant documentation

## Preparing for WordPress.org Submission

### 1. Update Plugin Details

Edit `wp-blog-mailer.php` header:

```php
/**
 * Plugin Name: WP Blog Mailer
 * Plugin URI:  https://wordpress.org/plugins/wpblog-mailer/  # Update after submission
 * Description: Simple automated newsletter system for WordPress.
 * Version:     1.0.0
 * Author:      Your Name
 * Author URI:  https://creativenoesis.com
 * ...
 */
```

### 2. Update readme.txt

Edit `readme.txt`:

```
Contributors: yourusername  # Your WordPress.org username
```

Update these URLs:
- `[WP Blog Mailer Pro](https://creativenoesis.com/wp-blog-mailer/)`
- Support links
- Screenshot descriptions

### 3. Update Upgrade URLs

Find and replace in all files:

```bash
# Search for:
https://creativenoesis.com/wp-blog-mailer/

# Replace with your actual premium URL:
https://example.com/premium/
```

Files to check:
- `wp-blog-mailer.php` (upgrade notice)
- `includes/Core/Plugin.php` (upgrade page)
- `includes/Common/Utilities/free-helpers.php` (wpbm_get_upgrade_url)
- `readme.txt` (multiple locations)

### 4. Add Screenshots

WordPress.org requires screenshots:

1. Create `assets/` folder (separate from plugin assets)
2. Add numbered screenshots: `screenshot-1.png`, `screenshot-2.png`, etc.
3. Each screenshot should be 1200x900px or similar 4:3 ratio

Screenshot checklist:
- [ ] Dashboard overview
- [ ] Subscriber list management
- [ ] Settings page
- [ ] Send log
- [ ] Subscription form example

### 5. Security Audit

Before submission, verify:

- [ ] All AJAX handlers have nonce checks
- [ ] All database queries use `$wpdb->prepare()`
- [ ] All user inputs are sanitized
- [ ] All outputs are escaped
- [ ] No `eval()` or similar dangerous functions
- [ ] File upload security (if applicable)

### 6. Test Thoroughly

- [ ] Install on fresh WordPress installation
- [ ] Test all core features
- [ ] Add/remove subscribers
- [ ] Send test newsletter
- [ ] Check cron jobs
- [ ] Test uninstall (data cleanup)
- [ ] Test on PHP 7.4, 8.0, 8.1, 8.2
- [ ] Test on WordPress 5.8, 6.0, 6.4

### 7. Licensing

WordPress.org requires GPL-compatible license:

- [ ] Ensure all code is GPL v2 or later
- [ ] Check third-party library licenses
- [ ] Update LICENSE file if needed

## WordPress.org Submission Checklist

- [ ] Plugin tested and working
- [ ] readme.txt complete and accurate
- [ ] Screenshots added to assets folder
- [ ] All URLs updated (no placeholders)
- [ ] Security audit passed
- [ ] GPL-compliant licensing
- [ ] Unique plugin slug chosen
- [ ] WordPress.org account created
- [ ] SVN repository access requested

## Submitting to WordPress.org

1. **Request Plugin Submission**
   - Go to https://wordpress.org/plugins/developers/add/
   - Submit your plugin URL (GitHub or ZIP)
   - Wait for approval (usually 2-10 days)

2. **Receive SVN Access**
   - You'll receive an email with SVN repository URL
   - URL format: `https://plugins.svn.wordpress.org/your-plugin-slug/`

3. **Upload to SVN**

```bash
# Checkout SVN repo
svn co https://plugins.svn.wordpress.org/wp-blog-mailer/ wpbm-svn
cd wpbm-svn

# Copy files to trunk
cp -r ../wpblog-mailer-free/* trunk/

# Copy assets (screenshots, banners)
cp ../assets/screenshot-*.png assets/

# Add files
svn add trunk/* assets/*

# Commit
svn ci -m "Initial version 1.0.0"

# Tag the release
svn cp trunk tags/1.0.0
svn ci -m "Tagging version 1.0.0"
```

4. **Plugin Goes Live**
   - Usually live within minutes after SVN commit
   - Check https://wordpress.org/plugins/your-slug/

## Maintaining Two Versions

### Strategy 1: Separate Repositories

**Premium Repo** (Private)
- Full codebase with Freemius
- All Pro/Starter features
- Sold on your website

**Free Repo** (Public)
- Subset of features
- WordPress.org version
- Community contributions welcome

**Workflow:**
1. Develop features in premium repo
2. Run build script to generate free version
3. Manually review and commit to free repo
4. Tag releases separately

### Strategy 2: Build Script Automation

Use `build-free-version.sh` to automate:

```bash
# After updating premium version
./build-free-version.sh

# Review changes
cd wpblog-mailer-free
git diff

# Commit and tag
git add .
git commit -m "Update free version to match premium 2.1.0"
git tag v1.1.0
git push origin main --tags

# Deploy to WordPress.org SVN
```

### Strategy 3: Monorepo with Build Pipeline

Keep both versions in one repo:

```
/wpblog-mailer/
├── premium/          # Full version
├── free/             # Free version
├── common/           # Shared code
└── build-scripts/    # Automation
```

Use CI/CD (GitHub Actions) to:
- Auto-build free version
- Run tests on both
- Tag releases
- Deploy to WordPress.org

## Support Strategy

### Free Version Support

- WordPress.org support forums
- GitHub issues (if public repo)
- Documentation and FAQs
- Community-driven

### Premium Version Support

- Dedicated support portal
- Priority email support
- Private ticket system
- Included in license price

## Marketing Strategy

### Free Version as Lead Generator

The free version should:
1. Provide real value (not crippled)
2. Showcase your plugin quality
3. Build trust with users
4. Create natural upgrade path

### Upgrade Conversion

Optimize for conversions:
- **Dashboard notice**: Dismissible, non-intrusive
- **Upgrade page**: Beautiful, clear value proposition
- **Feature gates**: "Upgrade to unlock" messages
- **Email campaigns**: Newsletter to free users
- **Reviews**: Ask happy free users for reviews

### Pricing Transparency

Be clear about:
- What's included in free
- What requires premium
- Pricing tiers (if multiple)
- No hidden costs

## Legal Considerations

### GPL Compliance

WordPress.org requires:
- GPL v2 or later license
- No license keys for core functionality
- No phone-home features

Your premium version can:
- Use Freemius for licensing
- Check license validity
- Offer updates only to paying customers

### Trademark

- Don't use "WordPress" in plugin name
- Use "for WordPress" or "WP" instead
- Follow WordPress.org branding guidelines

### Privacy

- Disclose data collection in readme.txt
- GDPR compliance for EU users
- Clear privacy policy

## Troubleshooting

### Build Script Errors

**Error**: Permission denied
```bash
chmod +x build-free-version.sh
```

**Error**: Files not copying
- Check source paths exist
- Verify folder permissions

### Plugin Not Activating

**Error**: Autoloader not found
- Run `composer install` in plugin folder
- Check `includes/Core/Autoloader.php` exists

**Error**: Class not found
- Check namespace matches folder structure
- Verify `ServiceContainer.php` loads correctly

### SVN Issues

**Error**: SVN authentication failed
- Use WordPress.org credentials
- Check SVN URL is correct

**Error**: Files not appearing on WordPress.org
- Wait 10-15 minutes for cache to clear
- Check SVN commit succeeded
- Verify files are in `/trunk/` folder

## Need Help?

- **WordPress.org Plugin Handbook**: https://developer.wordpress.org/plugins/
- **SVN Guide**: https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/
- **Plugin Guidelines**: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/

## Next Steps

1. ✅ Review all files in `wpblog-mailer-free/`
2. ✅ Update URLs and branding
3. ✅ Test plugin locally
4. ✅ Run security audit
5. ✅ Create screenshots
6. ✅ Submit to WordPress.org
7. ✅ Plan marketing strategy

Good luck with your WordPress.org submission! 🚀
