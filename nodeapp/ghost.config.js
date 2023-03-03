module.exports = {
    apps: (function() {
        const fs = require('fs');

        // Load default PM2 compatible nodeapp configuration.
        let nodeapp = require('/usr/local/hestia/plugins/nodeapp/nodeapp.js')(__filename);
        nodeapp.script = 'current/index.js';

        // Update the env variable based on the mode (production or debug)
        if (nodeapp.hasOwnProperty('_debugPort')) {
            nodeapp.env.NODE_ENV = 'development';
        }else{
            nodeapp.env.NODE_ENV = 'production';
        }
        return [nodeapp];
    })()
}