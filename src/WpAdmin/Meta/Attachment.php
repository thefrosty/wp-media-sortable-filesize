<?php

declare(strict_types=1);

namespace TheFrosty\WpMediaSortableFilesize\WpAdmin\Meta;

use TheFrosty\WpMediaSortableFilesize\ServiceProvider;
use TheFrosty\WpUtilities\Plugin\AbstractContainerProvider;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestInterface;
use TheFrosty\WpUtilities\Plugin\HttpFoundationRequestTrait;
use TheFrosty\WpUtilities\Utils\Viewable;
use WP_Post;
use function __;
use function add_meta_box;
use function wp_get_attachment_metadata;

/**
 * Class Attachment
 * @package TheFrosty\WpMediaSortableFilesize\WpAdmin\Meta
 */
class Attachment extends AbstractContainerProvider implements HttpFoundationRequestInterface
{

    use HttpFoundationRequestTrait, Viewable;

    /**
     * Add class hooks.
     */
    public function addHooks(): void
    {
        $this->addAction('add_meta_boxes_attachment', [$this, 'addMetaBoxes']);
    }

    /**
     * Register our attachment meta box.
     */
    protected function addMetaBoxes(): void
    {
        add_meta_box(
            esc_attr(self::class),
            __('Intermediate File Sizes', 'media-library-filesize'),
            function (WP_Post $post): void {
                $this->getView(ServiceProvider::WP_UTILITIES_VIEW)->render(
                    'admin/meta/attachment',
                    [
                        'metadata' => wp_get_attachment_metadata($post->ID),
                        'post' => $post,
                    ]
                );
            },
            'attachment',
            'side'
        );
    }
}
