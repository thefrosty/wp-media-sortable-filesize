<?php

declare(strict_types=1);

namespace TheFrosty\WpMediaSortableFilesize\WpAdmin;

use TheFrosty\WpMediaSortableFilesize\WpAdmin\Columns\FileSize;
use TheFrosty\WpUtilities\Plugin\AbstractContainerProvider;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestInterface;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestTrait;
use function __;
use function get_attached_file;
use function update_post_meta;
use function wp_filesize;

/**
 * Class BulkActions
 * @package Thefrosty\WpMediaSortableFilesize\WpAdmin
 */
class BulkActions extends AbstractContainerProvider implements HttpFoundationRequestInterface
{

    use HttpFoundationRequestTrait;

    final public const ACTION = 'generate_filesize';

    /**
     * Add class hooks.
     */
    public function addHooks(): void
    {
        $this->addFilter('bulk_actions-upload', [$this, 'bulkActions']);
        $this->addFilter('handle_bulk_actions-upload', [$this, 'handleBulkActions'], 10, 3);
    }

    protected function bulkActions(array $actions): array
    {
        $actions[self::ACTION] = __('Generate filesize meta', 'media-library-filesize');

        return $actions;
    }

    /**
     * Handle bulk action on upload.php (Media Library)
     * @param string $location
     * @param string $action
     * @param array $post_ids
     * @return string
     */
    protected function handleBulkActions(string $location, string $action, array $post_ids): string
    {
        if ($action !== self::ACTION) {
            return $location;
        }

        foreach ($post_ids as $post_id) {
            $file = get_attached_file($post_id);
            if (!$file) {
                continue;
            }
            update_post_meta($post_id, FileSize::META_KEY, wp_filesize($file));
        }

        // Schedule a cron event to update the cache count.
        Cron::scheduleSingleEventCount();

        return $location;
    }
}
