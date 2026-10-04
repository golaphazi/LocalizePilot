<?php
/**
 * A page builder that keeps its content somewhere other than post_content.
 *
 * Gutenberg stores a page in post_content, which LocalizePilot translates and
 * an editor can open per language. Elementor and Bricks store theirs in post
 * meta — a tree of elements and settings — and render from it, so a page built
 * with one of them has nothing in post_content to translate, and its
 * translation needs the same tree with the text replaced.
 *
 * An adapter answers four things: whether it is here, whether it built this
 * page, where its tree is kept, and which settings in that tree are words
 * rather than markup. Everything else — walking the tree, collecting text,
 * putting translations back — is shared, so a new builder is a short class.
 *
 * Which settings count as text is an allowlist, not a guess. A builder's
 * settings hold colours, widths, CSS classes, icon names and URLs beside the
 * words, and a translator cannot tell them apart; sending "fas fa-check" or
 * "boxed" to a translation provider would come back as something that breaks
 * the page.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Builders;

defined( 'ABSPATH' ) || exit;

abstract class Builder {
	/** Short identifier, e.g. "elementor". */
	abstract public function id(): string;

	/** The builder's name, as people know it. */
	abstract public function label(): string;

	/** Whether the builder is installed and running. */
	abstract public function available(): bool;

	/** Whether this post was built with it. */
	abstract public function handles( \WP_Post $post ): bool;

	/** The meta key holding the tree of elements. */
	abstract public function content_key(): string;

	/**
	 * Meta the translation needs beside the tree — edit mode, page settings —
	 * copied from the source as they are.
	 *
	 * @return array<int,string>
	 */
	abstract public function companion_keys(): array;

	/**
	 * Meta where the builder keeps rendered output rather than content.
	 *
	 * These belong to one language. On a translated request the translation's
	 * copy is read — never the source's, or the page would come back in the
	 * source language — and anything the builder writes is redirected onto the
	 * translation, or rendering a German page would leave German HTML in the
	 * English page's cache.
	 *
	 * @return array<int,string>
	 */
	public function cache_keys(): array {
		return array();
	}

	/**
	 * Settings that hold words, and which of those hold HTML.
	 *
	 * @return array<string,bool> Setting key => true when its value is HTML.
	 */
	abstract protected function text_keys(): array;

	/**
	 * The stored meta value as a tree.
	 *
	 * @param mixed $stored Meta value.
	 * @return array<int|string,mixed>
	 */
	public function decode( $stored ): array {
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * A tree as the meta value, in the shape get_post_meta returns.
	 *
	 * Unslashed: slashes belong to whoever writes it, since update_post_meta
	 * strips one level. decode( encode( $tree ) ) must give the tree back.
	 *
	 * @param array<int|string,mixed> $tree Tree.
	 * @return mixed
	 */
	public function encode( array $tree ) {
		return $tree;
	}

	/**
	 * Where this builder opens a post for editing, or '' when it cannot.
	 */
	public function edit_url( int $post_id ): string {
		return '';
	}

	/**
	 * Let this builder edit a post type.
	 *
	 * Translation records are a post type of LocalizePilot's own, and a
	 * builder only offers to edit the types it was told about. Each adapter
	 * knows how its own builder is told; nothing outside has to.
	 */
	public function enable_editing( string $post_type ): void {}

	/* ---------------------------------------------------------------------
	 * The shared walk
	 * ------------------------------------------------------------------ */

	/**
	 * Every piece of text in a tree, keyed by where it sits.
	 *
	 * The key is the path through the tree — "0.elements.1.settings.title" —
	 * so a translation can be put back exactly where it came from, whatever
	 * the builder calls its parts.
	 *
	 * @param array<int|string,mixed> $tree Tree.
	 * @return array<string,array{text:string,html:bool}>
	 */
	public function strings( array $tree ): array {
		$found = array();

		$this->walk(
			$tree,
			'',
			function ( string $path, string $key, string $value ) use ( &$found ): ?string {
				$keys = $this->keys();

				if ( ! isset( $keys[ $key ] ) || '' === trim( $value ) ) {
					return null;
				}

				// Something with no letters or digits — "—", "100%" — has
				// nothing to translate and would waste a provider call.
				if ( ! preg_match( '/[\p{L}]/u', $value ) ) {
					return null;
				}

				$found[ $path ] = array(
					'text' => $value,
					'html' => (bool) $keys[ $key ] || false !== strpos( $value, '<' ),
				);

				return null;
			}
		);

		return $found;
	}

	/**
	 * The same tree with translations in place of the text at those paths.
	 *
	 * @param array<int|string,mixed> $tree         Tree.
	 * @param array<string,string>    $translations Path => translated text.
	 * @return array<int|string,mixed>
	 */
	public function replace( array $tree, array $translations ): array {
		return $this->walk(
			$tree,
			'',
			static function ( string $path, string $key, string $value ) use ( $translations ): ?string {
				return $translations[ $path ] ?? null;
			}
		);
	}

	/**
	 * The text settings this builder translates, after add-ons have had a say.
	 *
	 * @return array<string,bool>
	 */
	public function keys(): array {
		/**
		 * Filter which settings a page builder's translation covers.
		 *
		 * Keyed by setting name; true when the value is HTML rather than
		 * plain text. A theme or add-on widget with its own text setting adds
		 * it here.
		 *
		 * @param array<string,bool> $keys    Setting key => holds HTML.
		 * @param string             $builder Builder id.
		 */
		return (array) apply_filters( 'localizepilot_builder_text_keys', $this->text_keys(), $this->id() );
	}

	/**
	 * Walk every string in a tree, optionally replacing it.
	 *
	 * @param array<int|string,mixed> $node    Tree or branch.
	 * @param string                  $path    Path so far.
	 * @param callable                $visitor function( string $path, string $key, string $value ): ?string
	 * @return array<int|string,mixed>
	 */
	protected function walk( array $node, string $path, callable $visitor ): array {
		foreach ( $node as $key => $value ) {
			$key_path = '' === $path ? (string) $key : $path . '.' . $key;

			if ( is_array( $value ) ) {
				$node[ $key ] = $this->walk( $value, $key_path, $visitor );
				continue;
			}

			if ( ! is_string( $value ) ) {
				continue;
			}

			$replacement = $visitor( $key_path, (string) $key, $value );

			if ( null !== $replacement ) {
				$node[ $key ] = $replacement;
			}
		}

		return $node;
	}
}
