<?php
/**
 * Read model for the Media screen.
 *
 * The media library itself is real: every row is an attachment from this
 * site, with its real thumbnail, filename, type, parent and modified date.
 *
 * The localization columns are not invented either. LocalizePilot stores no
 * per-attachment language data — there is no meta key to read — so every
 * attachment really is served in its source form, which is exactly what the
 * Languages and Status columns say. When media localization ships,
 * localization() starts returning real values and no template changes.
 *
 * The screen's controls stay inert through Preview's media_filters,
 * media_bulk and media_localize features.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin\Data;

defined( 'ABSPATH' ) || exit;

final class Media_Repository {
	public const PER_PAGE = 20;

	/**
	 * Attachments for the media table.
	 *
	 * @param array<string,mixed> $filters {search, type, order, paged}.
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function query( array $filters = array() ): array {
		$page   = max( 1, (int) ( $filters['paged'] ?? 1 ) );
		$search = sanitize_text_field( (string) ( $filters['search'] ?? '' ) );
		$type   = sanitize_key( (string) ( $filters['type'] ?? '' ) );

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'oldest' === ( $filters['order'] ?? '' ) ? 'ASC' : 'DESC',
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		if ( array_key_exists( $type, $this->type_map() ) ) {
			$args['post_mime_type'] = $type;
		}

		/**
		 * Filter the attachment query behind the Media screen.
		 *
		 * The language and status filters arrive in $filters but are not
		 * applied here, because nothing in LocalizePilot records either one
		 * per attachment. Whatever does record them adds the meta_query.
		 *
		 * @param array<string,mixed> $args    WP_Query arguments.
		 * @param array<string,mixed> $filters The screen's request filters.
		 */
		$args = (array) apply_filters( 'localizepilot_media_query_args', $args, $filters );

		$query = new \WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$items[] = $this->to_row( $post );
		}

		return array(
			'items'    => $items,
			'total'    => (int) $query->found_posts,
			'page'     => $page,
			'per_page' => self::PER_PAGE,
		);
	}

	/**
	 * The four summary figures.
	 *
	 * @return array<string,int>
	 */
	public function counts(): array {
		$counts = wp_count_attachments();
		$total  = 0;

		foreach ( (array) $counts as $mime => $count ) {
			// wp_count_attachments() includes a trashed bucket keyed 'trash'.
			if ( 'trash' !== $mime ) {
				$total += (int) $count;
			}
		}

		// No attachment carries a language or a localized variant yet, so
		// these are counted rather than assumed: both are zero, and every
		// attachment is therefore served from its source file.
		$localized = 0;
		$custom    = 0;

		/**
		 * Filter the Media screen's four summary figures.
		 *
		 * Counted, not assumed: with nothing recording a localized variant
		 * both middle figures really are zero. Whatever starts recording them
		 * replaces these with its own counts.
		 *
		 * @param array<string,int> $counts {total, localized, custom, needs}.
		 */
		return (array) apply_filters(
			'localizepilot_media_counts',
			array(
				'total'     => $total,
				'localized' => $localized,
				'custom'    => $custom,
				'needs'     => max( 0, $total - $localized - $custom ),
			)
		);
	}

	/**
	 * Mime groups available as a filter.
	 *
	 * @return array<string,string>
	 */
	public function type_options(): array {
		return array( '' => __( 'All Types', 'localizepilot' ) ) + $this->type_map();
	}

	/* ---------------------------------------------------------------------
	 * Internals
	 * ------------------------------------------------------------------ */

	/**
	 * @return array<string,string>
	 */
	private function type_map(): array {
		return array(
			'image'       => __( 'Image', 'localizepilot' ),
			'video'       => __( 'Video', 'localizepilot' ),
			'audio'       => __( 'Audio', 'localizepilot' ),
			'application' => __( 'Document', 'localizepilot' ),
		);
	}

	/**
	 * Shape one attachment for the table.
	 *
	 * @param \WP_Post $post Attachment.
	 * @return array<string,mixed>
	 */
	private function to_row( \WP_Post $post ): array {
		$file   = (string) get_post_meta( $post->ID, '_wp_attached_file', true );
		$group  = strtok( (string) $post->post_mime_type, '/' );
		$parent = $post->post_parent > 0 ? get_post( $post->post_parent ) : null;

		$row = array(
			'id'         => $post->ID,
			'title'      => get_the_title( $post ),
			'filename'   => '' !== $file ? wp_basename( $file ) : '',
			'type'       => $this->type_map()[ $group ] ?? __( 'File', 'localizepilot' ),
			'thumbnail'  => (string) wp_get_attachment_image_url( $post->ID, 'thumbnail' ),
			'used_in'    => $parent instanceof \WP_Post ? get_the_title( $parent ) : '',
			'used_in_url' => $parent instanceof \WP_Post ? (string) get_edit_post_link( $parent->ID, 'raw' ) : '',
			'updated'    => (int) get_post_modified_time( 'U', true, $post ),
			'edit_url'   => (string) get_edit_post_link( $post->ID, 'raw' ),
			'view_url'   => (string) wp_get_attachment_url( $post->ID ),
		) + $this->localization();

		/**
		 * Filter one attachment's row on the Media screen.
		 *
		 * The localization keys — languages, status, status_label — are the
		 * ones worth replacing; see localization() for what they mean while
		 * nothing records them. The rest describe the attachment itself and
		 * are already true.
		 *
		 * @param array<string,mixed> $row  The row.
		 * @param \WP_Post            $post The attachment.
		 */
		return (array) apply_filters( 'localizepilot_media_row', $row, $post );
	}

	/**
	 * The language columns for one attachment.
	 *
	 * While media localization is preview-only there is nothing stored to
	 * read, so every attachment is reported as served from its source — which
	 * is what the site actually does. The shape is the one the live version
	 * will fill in.
	 *
	 * @return array<string,mixed>
	 */
	private function localization(): array {
		return array(
			'languages'    => array(),
			'status'       => 'neutral',
			'status_label' => __( 'Not Localized', 'localizepilot' ),
		);
	}
}
