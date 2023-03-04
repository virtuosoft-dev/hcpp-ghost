module.exports = {
    apps: (function() {
        const fs = require('fs');

        // Load default PM2 compatible nodeapp configuration.
        let nodeapp = require('/usr/local/hestia/plugins/nodeapp/nodeapp.js')(__filename);
        nodeapp.script = 'current/index.js';
        let domain = nodeapp._domain;
        let user = nodeapp._user;

        // Update the env variable based on the mode (production or debug)
        nodeapp.env = {};
        if (nodeapp.hasOwnProperty('_debugPort')) {
            nodeapp.env.NODE_ENV = 'development';
        }else{
            nodeapp.env.NODE_ENV = 'production';
        }

        // Get url, account for SSL, subfolder
        let root = __dirname.replace(/.*\/nodeapp/, '').trim();
        if (!root.startsWith('/')) root = '/' + root;

        // Check for SSL
        let url = 'http://';
        let sslPath = '/home/' + user + '/conf/web/' + domain + '/ssl';
        if (fs.existsSync(sslPath)) {
            if (fs.readdirSync(sslPath).length !== 0) {
                url = 'https://';
            }
        }
        url += domain + root;
        let contentPath = '/home/' + user + '/web/' + domain + '/nodeapp' + root + '/content';
        let file = __dirname + '/config.' + nodeapp.env.NODE_ENV + '.json';
        let config = JSON.parse(fs.readFileSync(file));
        config.url = url;
        config.server.port = nodeapp._port;
        config.paths.contentPath = contentPath;
        fs.writeFileSync(file, JSON.stringify(config, null, 2));
        return [nodeapp];
    })()
}