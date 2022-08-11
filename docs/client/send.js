const net = require('net');

/**
 * using default plaintext TCP protocol
 */

// email sending with send confirmation (plaintext TCP)
function getIpInfo(ip) { 
	const client = new net.Socket();
	client.connect(3000, '127.0.0.1', () => {
		client.write(JSON.stringify({
			"ip": ip
		}));
	});
	client.on('data', (data) => {
		const response = JSON.parse(data);
		if (response.status && response.status === 'success') {
			// ip geo data
			console.log(response.data);
		} else {
			console.log('ipinfo failed!');
		}
		client.destroy();
	});
	client.on('error', (err) => {
		throw err;
	});
}

/**
 * using SSL protocol
 */

const fs = require('fs');
const tls = require('tls');

var options = {
    key: fs.readFileSync('selfsigned.key'),
    cert: fs.readFileSync('selfsigned.crt'),
    rejectUnauthorized: false // allow self-signed certs
};

// email sending with send confirmation (SSL)
function getIpInfoOverSSL(ip) { 
	const client = tls.connect(3000, '127.0.0.1', options, () => {
		client.write(JSON.stringify({
			"ip": ip
		}));
	});
	client.on('data', (data) => {
		const response = JSON.parse(data);
		if (response.status && response.status === 'success') {
			// ip geo data
			console.log(response.data);
		} else {
			console.log('ipinfo failed!');
		}
		client.destroy();
	});
	client.on('error', (err) => {
		throw err;
	});
}
