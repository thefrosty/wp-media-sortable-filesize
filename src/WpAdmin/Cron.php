<?php

declare(strict_types=1);

namespace TheFrosty\WpMediaSortableFilesize\WpAdmin;

use TheFrosty\WpMediaSortableFilesize\WpAdmin\Columns\FileSize;
use TheFrosty\WpUtilities\Api\WpQueryTrait;
use TheFrosty\WpUtilities\Plugin\AbstractContainerProvider;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestInterface;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestTrait;
use WP_Error;
use function get_attached_file;
use function get_post_meta;
use function is_readable;
use function is_string;
use function set_time_limit;
use function set_transient;
use function strtotime;
use function time;
use function update_post_meta;
use function wp_filesize;
use function wp_next_scheduled;
use function wp_schedule_single_event;
use const MINUTE_IN_SECONDS;
use const WEEK_IN_SECONDS;

/**
 * Class Cron
 * @package TheFrosty\WpMediaSortableFilesize\WpAdmin\Columns
 */
class Cron extends AbstractContainerProvider implements HttpFoundationRequestInterface
{

    use HttpFoundationRequestTrait, WpQueryTrait;

    final public const HOOK_UPDATE_META = 'wp_media_sortable_filesize';
    final public const HOOK_UPDATE_COUNT = 'wp_media_sortable_filesize';
    final public const HOOK_UPDATE_ID = 'wp_media_sortable_filesize_id';

    final public const TRANSIENT = 'wp_media_sortable_filesize_count';

    /**
     * Helper method to trigger a re-count event.
     */
    public static function scheduleSingleEventCount(): void
    {
        if (!wp_next_scheduled(Cron::HOOK_UPDATE_COUNT)) {
            wp_schedule_single_event(strtotime('now'), Cron::HOOK_UPDATE_COUNT);
        }
    }

    /**
     * Add class hooks.
     */
    public function addHooks(): void
    {
        $this->addAction(self::HOOK_UPDATE_META, [$this, 'updateAllAttachmentsPostMeta']);
        $this->addAction(self::HOOK_UPDATE_COUNT, [$this, 'updateAttachmentCount']);
        $this->addAction(self::HOOK_UPDATE_ID, [$this, 'updateAttachmentIdMeta']);
    }

    /**
     * Run our update attachments post meta method.
     */
    protected function updateAllAttachmentsPostMeta(): void
    {
        $seconds = 60;
        set_time_limit($seconds);
        $time = time();
        $attachments = $this->getAllAttachments();

        if (empty($attachments) || !count($attachments)) {
            return;
        }

        $error = new WP_Error();
        foreach ($attachments as $attachment) {
            // Break out of the loop if whe have reached >= $seconds.
            if (time() - $time > $seconds) {
                break;
            }
            if (!get_post_meta($attachment, FileSize::META_KEY, true)) {
                $file = get_attached_file($attachment);
                // Make sure it's readable (on file-system).
                if (!is_string($file) || !is_readable($file)) {
                    $error->add('not_found', 'File not found');
                    continue;
                }
                update_post_meta($attachment, FileSize::META_KEY, wp_filesize($file));
            }
        }
    }

    /**
     * Run our attachment count cache cron.
     */
    protected function updateAttachmentCount(): void
    {
        $this->getAllAttachments();
    }

    /**
     * Update the attachment filesize post meta.
     */
    protected function updateAttachmentIdMeta(int|string $attachment_id): void
    {
        $previous = get_post_meta($attachment_id, FileSize::META_KEY, true);
        $file = get_attached_file($attachment_id);
        // Make sure it's readable (on file-system).
        if (!is_string($file) || !is_readable($file)) {
            return;
        }
        update_post_meta($attachment_id, FileSize::META_KEY, wp_filesize($file), $previous);
    }

    /**
     * Get all attachments query.
     * @return int[] array
     */
    private function getAllAttachments(): array
    {
        $args = [
            'post_status' => 'inherit',
            'meta_query' => [
                [
                    'key' => FileSize::META_KEY,
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ];
        $attachments = $this->wpQueryGetAllIdsCached('attachment', $args, MINUTE_IN_SECONDS);
        set_transient(self::TRANSIENT, count($attachments), WEEK_IN_SECONDS);

        return $attachments;
    }
}
