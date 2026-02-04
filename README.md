# Check Your Redirects and Status Code

Project includes two versions: PHP (web-based) and JavaScript (command-line).

## PHP Version (Web-based)

### Usage

1. Ensure you have PHP and cURL installed.
2. Run PHP server:
   ```
   php -S localhost:8000
   ```
3. Open browser and visit `http://localhost:8000`
4. Enter URL to check and click "Check".

### Features

- Checks redirect chain of URL
- Detects 301/302 redirects, meta refresh, and JavaScript redirects
- Supports single URL or batch check from a text file (one URL per line)
- Simple web interface

## JavaScript Version (Command-line)

### Usage

1. Ensure you have Node.js installed.
2. Run script:
   ```
   node check301.js <URL>
   ```
   Example:
   ```
   node check301.js https://example.com
   ```

### Features

- Checks redirect chain from command line
- Displays each redirect step
- Detects 301 redirects in chain

## Notes

- Both versions follow up to 10 redirects.
- Ensure URL is valid (starts with http:// or https://).
- SSL certificate verification is disabled for HTTPS URLs to avoid local certificate issues.