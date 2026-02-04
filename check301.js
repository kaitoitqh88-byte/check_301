const https = require('https');
const http = require('http');
const url = require('url');

function checkRedirects(inputUrl, callback) {
    const redirects = [];
    let currentUrl = inputUrl;
    const maxRedirects = 10;

    function followRedirect() {
        if (redirects.length >= maxRedirects) {
            callback(redirects, false);
            return;
        }

        const parsedUrl = url.parse(currentUrl);
        const protocol = parsedUrl.protocol === 'https:' ? https : http;

        const options = {
            hostname: parsedUrl.hostname,
            path: parsedUrl.path,
            method: 'GET',
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            }
        };

        const req = protocol.request(options, (res) => {
            const statusCode = res.statusCode;
            redirects.push({ url: currentUrl, code: statusCode });

            if (statusCode === 301 || statusCode === 302) {
                let location = res.headers.location;
                if (location) {
                    if (location.startsWith('/')) {
                        location = `${parsedUrl.protocol}//${parsedUrl.host}${location}`;
                    } else if (!location.startsWith('http')) {
                        location = url.resolve(currentUrl, location);
                    }
                    currentUrl = location;
                    followRedirect();
                } else {
                    callback(redirects, false);
                }
            } else {
                callback(redirects, statusCode === 301);
            }
        });

        req.on('error', (err) => {
            console.error('Error:', err.message);
            callback(redirects, false);
        });

        req.setTimeout(10000, () => {
            req.abort();
            console.error('Timeout');
            callback(redirects, false);
        });

        req.end();
    }

    followRedirect();
}

const inputUrl = process.argv[2];
if (!inputUrl) {
    console.log('Usage: node check301.js <URL>');
    process.exit(1);
}

checkRedirects(inputUrl, (redirects, has301) => {
    console.log('Kết quả kiểm tra:');
    console.log('URL ban đầu:', inputUrl);
    console.log('Chuỗi redirect:');
    redirects.forEach((redirect, index) => {
        console.log(`${index + 1}. ${redirect.url} -> ${redirect.code}`);
    });
    console.log('URL cuối cùng:', redirects[redirects.length - 1].url);
    if (has301) {
        console.log('301 redirect được phát hiện trong chuỗi!');
    } else {
        console.log('Không có 301 redirect trong chuỗi.');
    }
});