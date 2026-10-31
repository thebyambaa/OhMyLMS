const { defineConfig } = require('@playwright/test');
const path = require('node:path');
const php = process.env.OHMYLMS_PHP_BIN || 'php';
// The server graders need mbstring; without a php.ini pass e.g. ["-d","extension_dir=C:/php/ext","-d","extension=mbstring"].
const args = JSON.parse(process.env.OHMYLMS_PHP_ARGS || '[]').map(value => `"${value}"`).join(' ');
module.exports = defineConfig({
 testDir:'browser', testMatch:'mathlive.spec.cjs', timeout:30000, workers:1,
 use:{baseURL:'http://127.0.0.1:8110',channel:'msedge',headless:true},
 webServer:{command:`"${php}" ${args} -S 127.0.0.1:8110 "${path.join(__dirname,'fixtures/mathlive.php')}"`,url:'http://127.0.0.1:8110/fixture',reuseExistingServer:false,timeout:15000},
});
