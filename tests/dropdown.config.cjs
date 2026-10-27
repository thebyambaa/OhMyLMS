const { defineConfig } = require('@playwright/test');
const path = require('node:path');
const php = process.env.OHMYLMS_PHP_BIN || 'php';
const args = JSON.parse(process.env.OHMYLMS_PHP_ARGS || '[]').map(value => `"${value}"`).join(' ');
module.exports = defineConfig({
 testDir:'browser',testMatch:'dropdown.spec.cjs',workers:1,timeout:30000,
 use:{baseURL:'http://127.0.0.1:8112',channel:'msedge',headless:true},
 webServer:{command:`"${php}" ${args} -S 127.0.0.1:8112 "${path.join(__dirname,'fixtures/dropdown.php')}"`,url:'http://127.0.0.1:8112/fixture',reuseExistingServer:false,timeout:15000},
});
