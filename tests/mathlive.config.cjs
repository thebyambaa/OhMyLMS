const { defineConfig } = require('@playwright/test');
const path = require('node:path');
const php = process.env.OHMYLMS_PHP_BIN || 'php';
module.exports = defineConfig({
 testDir:'browser', testMatch:'mathlive.spec.cjs', timeout:30000, workers:1,
 use:{baseURL:'http://127.0.0.1:8110',channel:'msedge',headless:true},
 webServer:{command:`"${php}" -S 127.0.0.1:8110 "${path.join(__dirname,'fixtures/mathlive.php')}"`,url:'http://127.0.0.1:8110/fixture',reuseExistingServer:false,timeout:15000},
});
