<?php
/**
 * Fixture for tests/browser/gamification.spec.cjs. Disposable WordPress database only.
 * Usage: php gamification-browser-fixture.php on | off | restore <state-json>
 * `on` and `off` switch the Gamification add-on and print what the option held before (the state JSON);
 * `restore` puts that back, so the spec leaves the site as it found it.
 */
if (PHP_SAPI !== 'cli') { exit; }
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
$config = json_decode(file_get_contents(getenv('OHMYLMS_TEST_CREDENTIALS')), true);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8099';
require $config['site'] . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || DB_NAME !== 'ohmylms_source_test') { throw new RuntimeException('Requires disposable test site'); }

$integrations = get_option('ohmylms_integrations', []);
$integrations = is_array($integrations) ? $integrations : [];
$action = $argv[1] ?? '';
$before = ['had' => array_key_exists('gamification', $integrations), 'gamification' => $integrations['gamification'] ?? null];
if ($action === 'on' || $action === 'off') {
    $current = is_array($integrations['gamification'] ?? null) ? $integrations['gamification'] : [];
    $integrations['gamification'] = array_merge($current, ['is_enable' => $action === 'on']);
    update_option('ohmylms_integrations', $integrations);
    echo json_encode($before), "\n";
} elseif ($action === 'restore') {
    $state = json_decode($argv[2] ?? '', true);
    if (!is_array($state) || !array_key_exists('had', $state)) { throw new RuntimeException('Pass the state JSON that on or off printed'); }
    if ($state['had']) { $integrations['gamification'] = $state['gamification']; } else { unset($integrations['gamification']); }
    update_option('ohmylms_integrations', $integrations);
    echo json_encode(['restored' => true]), "\n";
} else {
    throw new RuntimeException('Use on, off or restore <state-json>');
}
