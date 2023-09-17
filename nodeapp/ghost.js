/**
 * Our shim for ghost and PM2 compatibility.
 */
const { exec } = require('child_process');
const startCmd = 'ghost start';
const stopCmd = 'ghost stop';
const nvmCmd = 'bash -c "export NVM_DIR=/opt/nvm && source /opt/nvm/nvm.sh && nvm use && ';

const start = () => {
  console.log('Starting ghost...');
  exec(nvmCmd + startCmd + '"', (error, stdout, stderr) => {
    if (error) {
      console.error(`Error starting ghost: ${error}`);
    } else {
      console.log(`ghost started: ${stdout}`);
    }
  });
};

const stop = () => {
  console.log('Stopping ghost...');
  exec(nvmCmd + stopCmd + '"', (error, stdout, stderr) => {
    if (error) {
      console.error(`Error stopping ghost: ${error}`);
    } else {
      console.log(`ghost stopped: ${stdout}`);
    }
    process.exit();
  });
};

// Start the ghost process
start();

// Listen for SIGINT signal
process.on('SIGINT', () => {
  console.log('Received SIGINT signal, stopping ghost...');
  stop();
});
