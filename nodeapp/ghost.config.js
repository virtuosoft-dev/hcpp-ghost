/**
 * Get compatible PM2 app config object with automatic support for .nvmrc, 
 * and port allocation.
 */
module.exports = {
    apps: (function() {
        let nodeapp = require('/usr/local/hestia/plugins/nodeapp/nodeapp.js')(__filename);
        nodeapp.script = nodeapp.cwd + '/current/index.js';

        // Update the env variable based on the mode; production or debug (.debug file present)
        nodeapp.env = {};
        if (nodeapp.hasOwnProperty('_debugPort')) {
            nodeapp.env.NODE_ENV = 'development';
        }else{
            nodeapp.env.NODE_ENV = 'production';
        }
        return [nodeapp];
    })()
}