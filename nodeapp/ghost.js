/**
 * Our shim for ghost and PM2 compatibility.
 */
const { exec } = require('child_process');
const startCmd = 'ghost start';
const stopCmd = 'ghost stop';
const nvmCmd = 'cd ' + __dirname + ' && export NVM_DIR=/opt/nvm && . /opt/nvm/nvm.sh && nvm use && ';
const start = () => {
  console.log('Starting ghost...');
  exec(nvmCmd + startCmd, (error, stdout, stderr) => {
    if (error) {
      console.error(`Error starting ghost: ${error}`);
    } else {
      console.log(`ghost started: ${stdout}`);
    }
  });
};

const stop = () => {
  console.log('Stopping ghost...');
  exec(nvmCmd + stopCmd, (error, stdout, stderr) => {
    if (error) {
      console.error(`Error stopping ghost: ${error}`);
    } else {
      console.log(`ghost stopped: ${stdout}`);
    }
  });
};

// Start the ghost process
start();

// Listen for custom 'shutdown' message from the parent process
process.on('message', (message) => {
  if (message === 'shutdown') {
    console.log('Received shutdown message, stopping...');
    stop();
  }
});
