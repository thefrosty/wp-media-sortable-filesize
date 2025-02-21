<?php
/**
 * Plugin Name: Media Sortable Filesize
 * Description: Improve your Media Library functionality by introducing a new column that showcases the sizes of files.
 * Author: Austin Passy
 * Author URI: https://austin.passy.co/
 * Version: 1.1.0
 * Requires at least: 6.6
 * Tested up to: 6.7.2
 * Requires PHP: 8.1
 * Plugin URI: https://github.com/thefrosty/wp-media-sortable-filesize
 * GitHub Plugin URI: https://github.com/thefrosty/wp-media-sortable-filesize
 * Primary Branch: main
 * Release Asset: true
 */

namespace TheFrosty\WpMediaSortableFilesize;

defined('ABSPATH') || exit;

use Pimple\Container;
use TheFrosty\WpUtilities\Plugin\PluginFactory;
use TheFrosty\WpUtilities\WpAdmin\DisablePluginUpdateCheck;
use UnexpectedValueException;
use function add_action;
use function defined;
use function is_admin;
use function is_readable;

if (is_readable(__DIR__ . '/vendor/autoload.php')) {
    include_once __DIR__ . '/vendor/autoload.php';
}

$plugin = PluginFactory::create('media-sortable-filesize');
$container = $plugin->getContainer();
if (!$container instanceof Container) {
    throw new UnexpectedValueException('Unexpected object in Plugin container.');
}
$container->register(new ServiceProvider());

$plugin
    ->add(new WpAdmin\Cron())
    ->addOnHook(WpAdmin\BulkActions::class, 'init', admin_only: true)
    ->addOnHook(WpAdmin\Columns\FileSize::class, 'init', admin_only: true)
    ->addOnHook(WpAdmin\Meta\Attachment::class, 'admin_init', args: [$container]);

if (is_admin()) {
    $plugin->add(new DisablePluginUpdateCheck());
}

add_action('plugins_loaded', static function () use ($plugin): void {
    $plugin->initialize();
});
