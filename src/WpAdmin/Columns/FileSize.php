<?php

declare(strict_types=1);

namespace TheFrosty\WpMediaSortableFilesize\WpAdmin\Columns;

use TheFrosty\WpMediaSortableFilesize\WpAdmin\Cron;
use TheFrosty\WpUtilities\Plugin\AbstractContainerProvider;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestInterface;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestTrait;
use WP_Query;
use function absint;
use function add_query_arg;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function get_attached_file;
use function get_transient;
use function is_int;
use function is_numeric;
use function is_readable;
use function is_string;
use function printf;
use function size_format;
use function strtotime;
use function update_post_meta;
use function wp_count_posts;
use function wp_filesize;
use function wp_get_attachment_metadata;
use function wp_get_original_image_path;
use function wp_next_scheduled;
use function wp_nonce_url;
use function wp_schedule_single_event;
use function wp_verify_nonce;

/**
 * Class FileSize
 * @package TheFrosty\WpMediaSortableFilesize\WpAdmin\Columns
 */
class FileSize extends AbstractContainerProvider implements HttpFoundationRequestInterface
{

    use HttpFoundationRequestTrait;

    final public const META_KEY = '_filesize';
    final public const NONCE_ACTION = '_wp_attachment_metadata_filesize';
    final public const NONCE_NAME = 'nonce';

    /**
     * Get the attachment filesize.
     * @param int $attachment_id
     * @param string|null $message
     */
    public static function getFileSize(int $attachment_id, ?string $message = null): void
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (isset($metadata['filesize']) && is_int($metadata['filesize'])) {
            echo $message ?? '';
            echo esc_html(size_format($metadata['filesize']));
            return;
        }

        // If empty, try to read the attached file filesize via PHP.
        $file = get_attached_file($attachment_id);
        // Make sure it's readable (on file-system).
        if (!is_string($file) || !is_readable($file)) {
            esc_html_e('File not found', 'media-sortable-filesize');
            return;
        }

        echo $message ?? '';
        echo esc_html(size_format(wp_filesize($file)));
    }

    /**
     * Add class hooks.
     */
    public function addHooks(): void
    {
        $this->addFilter('manage_media_columns', [$this, 'manageMediaColumns']);
        $this->addFilter('manage_upload_sortable_columns', [$this, 'manageUploadSortableColumns']);
        $this->addAction('added_post_meta', [$this, 'addFilesizeMetadata'], 10, 4);
        $this->addAction('manage_media_custom_column', [$this, 'manageMediaCustomColumn'], 10, 2);
        $this->addAction('load-upload.php', [$this, 'loadUploadPhp']);
    }

    /**
     * Add our column to the media columns array.
     * @param array $columns
     * @return array
     */
    protected function manageMediaColumns(array $columns): array
    {
        $columns[self::META_KEY] = __('File Size', 'media-library-filesize');

        return $columns;
    }

    /**
     * Add our column to the media columns sortable array.
     * @param array $columns
     * @return array
     */
    protected function manageUploadSortableColumns(array $columns): array
    {
        $columns[self::META_KEY] = self::META_KEY;

        return $columns;
    }

    /**
     * Ensure file size meta gets added to new uploads.
     * @param int $meta_id The meta ID after successful update.
     * @param int $attachment_id ID of the object metadata is for.
     * @param string $meta_key Metadata key.
     * @param mixed $_meta_value Metadata value.
     */
    protected function addFilesizeMetadata(
        int $meta_id,
        int $attachment_id,
        string $meta_key,
        mixed $_meta_value
    ): void {
        if ($meta_key !== '_wp_attachment_metadata') {
            return;
        }

        $file = get_attached_file($attachment_id);
        if (!$file) {
            return;
        }
        update_post_meta($attachment_id, self::META_KEY, wp_filesize($file));
    }

    /**
     * Display our custom column data.
     * @param string $column_name
     * @param int $attachment_id
     */
    protected function manageMediaCustomColumn(string $column_name, int $attachment_id): void
    {
        if ($column_name === self::META_KEY) {
            // First, try to get our attachment custom key value.
            $filesize = get_post_meta($attachment_id, self::META_KEY, true);
            $has_meta = is_numeric($filesize);


            if ($has_meta) {
                echo esc_html($this->sizeFormat($filesize));
                $this->getIntermediateFilesizeHtml($attachment_id);
                return;
            }

            $warning = sprintf(
                '<span class="dashicons dashicons-warning" title="%s"></span>&nbsp;',
                esc_attr__('Missing attachment meta key, reading from meta or file.', 'media-sortable-filesize')
            );

            // Second, try to get the attachment metadata filesize.
            self::getFileSize($attachment_id, $warning);
        }
    }

    /**
     * Additional class hooks that should only trigger on the current page "upload.php".
     */
    protected function loadUploadPhp(): void
    {
        $this->addAction('restrict_manage_posts', [$this, 'restrictManagePosts']);
        $this->addAction('admin_print_styles', [$this, 'adminPrintStyles']);
        $this->addAction('pre_get_posts', [$this, 'preGetPosts']);
        $this->addAction('load-upload.php', function (): void {
            if (
                (!$this->getRequest()->query->has('action') || !$this->getRequest()->query->has(self::NONCE_NAME)) ||
                $this->getRequest()->query->get('action') !== self::NONCE_ACTION ||
                !wp_verify_nonce($this->getRequest()->query->get(self::NONCE_NAME), self::NONCE_ACTION)
            ) {
                return;
            }

            $scheduled = wp_next_scheduled(Cron::HOOK_UPDATE_META);
            if (!$scheduled) {
                $schedule = wp_schedule_single_event(strtotime('now'), Cron::HOOK_UPDATE_META);
            }
            wp_safe_redirect(
                remove_query_arg(
                    ['action', 'nonce'],
                    add_query_arg('scheduled', is_int($scheduled) ? $scheduled : $schedule ?? false)
                )
            );
            exit;
        }, 20);
    }

    /**
     * Add our button to the upload list view page.
     */
    protected function restrictManagePosts(): void
    {
        $total = wp_count_posts('attachment')->inherit;
        $count = get_transient(Cron::TRANSIENT);
        if ($count === false) {
            Cron::scheduleSingleEventCount();
        }
        printf(
            '<a href="%1$s" title="%3$s" class="button hide-if-no-js" style="margin-right:8px">%2$s</a>',
            esc_url(
                wp_nonce_url(
                    add_query_arg('action', self::NONCE_ACTION),
                    self::NONCE_ACTION,
                    self::NONCE_NAME
                )
            ),
            sprintf(
                '%1$s %2$s',
                esc_html__('Index Media', 'media-sortable-filesize'),
                !is_numeric($count) ? '' : "(Total: $count/$total)"
            ),
            esc_attr__('Schedule the media filesize meta index cron to run now', 'media-sortable-filesize')
        );
    }

    /**
     * Print our column style.
     */
    protected function adminPrintStyles(): void
    {
        $column = esc_attr(self::META_KEY);
        echo <<<STYLE
        <style>.fixed .column-$column {width: 10%}</style>
STYLE;
    }

    /**
     * Filter the attachments by filesize.
     * @param WP_Query $query
     */
    protected function preGetPosts(WP_Query $query): void
    {
        global $pagenow;

        if (
            !is_admin() ||
            $pagenow !== 'upload.php' ||
            !$query->is_main_query() ||
            !$this->getRequest()->query->has('orderby') ||
            $this->getRequest()->query->get('orderby') !== self::META_KEY
        ) {
            return;
        }

        $query->set('order', $this->getRequest()->query->get('order', 'desc'));
        $query->set('orderby', 'meta_value_num');
        $query->set('meta_key', self::META_KEY);
    }

    /**
     * Calculates the total generated sizes of all intermediate image sizes.
     * @param int $attachment_id
     * @return int
     */
    private function getIntermediateFilesize(int $attachment_id): int
    {
        $meta = wp_get_attachment_metadata($attachment_id);
        $size = 0;
        if (isset($meta['sizes'])) {
            foreach ($meta['sizes'] as $sizes) {
                $size += $sizes['filesize'] ?? 0;
            }
        }
        if (isset($meta['original_image']) && wp_get_original_image_path($attachment_id)) {
            $size += wp_filesize(wp_get_original_image_path($attachment_id));
        }

        return absint($size);
    }

    /**
     * Get the intermediate image sizes HTML output.
     * @param int $attachment_id
     */
    private function getIntermediateFilesizeHtml(int $attachment_id): void
    {
        $size = $this->getIntermediateFilesize($attachment_id);
        if ($size > 0) {
            printf(
                '<br><small title="%2$s">&plus; %1$s</small>',
                esc_html($this->sizeFormat($size)),
                esc_attr__('Additional intermediate image sizes added together.', 'media-sortable-filesize')
            );
        }
    }

    /**
     * Helper to get the size format with 2 decimals.
     * @param int|string $bytes
     * @return string
     */
    private function sizeFormat(int | string $bytes): string
    {
        return size_format($bytes);
    }
}
