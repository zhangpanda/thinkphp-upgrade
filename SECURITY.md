# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 0.3.x   | ✅ Current          |
| < 0.3   | ❌ No longer supported |

## Reporting a Vulnerability

If you discover a security vulnerability, please report it responsibly:

1. **Do NOT** open a public GitHub issue
2. Email: security@phplift.dev (or use GitHub Security Advisories)
3. Include: description, reproduction steps, and potential impact
4. We will acknowledge within 48 hours and provide a fix timeline within 7 days

## Security Considerations

### File System Access
- ThinkPHP-Upgrade reads and writes PHP source files on the local filesystem
- The `serve` command restricts file access to the specified project directory
- Path traversal is prevented via `realpath()` validation
- All file writes create `.bak` backups before modification

### Web UI (`tp-upgrade serve`)
- Intended for **local development use only** — do not expose to the internet
- CSRF protection on all state-changing API endpoints
- No authentication (assumes single-user local access)
- If you need remote access, place behind a reverse proxy with auth

### AI-Assisted Migration
- AI suggestions are never auto-applied without explicit user confirmation
- API keys are read from environment variables only
- No source code is stored or cached by the AI provider beyond the API call
- AI responses should be reviewed before accepting

### Generated Code
- ThinkPHP-Upgrade generates syntactically valid PHP but does NOT guarantee:
  - Runtime correctness
  - Security of generated patterns
  - Compatibility with all framework extensions
- Always review generated code and run your existing test suite after migration

## API Stability

This project follows [Semantic Versioning](https://semver.org/).

### Current Status: **Pre-1.0 (Unstable)**

Before v1.0, minor versions may include breaking changes. All breaking changes are documented in CHANGELOG.md.

### Stability Guarantees

| Component | Stability |
|-----------|-----------|
| CLI commands (`analyze`, `transform`, `migrate`) | 🟡 Stable |
| `tp-upgrade serve` (Web UI) | 🟠 Experimental |
| TP3→TP6 rules (7 rules) | 🟡 Stable |
| TP5→TP6 rules | 🟡 Stable |
| TP6→TP8 rules | 🟠 Experimental |
| Template migration | 🟠 Experimental |
| `RuleInterface` (custom rules) | 🟡 Stable |
| AI provider integration | 🟠 Experimental |
| JSON report format | 🟡 Stable |

### Custom Rule API

`RuleInterface` is considered stable. Custom rules written against this interface will continue to work across minor versions. We will provide deprecation notices before any breaking changes.
