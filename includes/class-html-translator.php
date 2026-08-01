<?php

namespace LocalizePilot;

defined( 'ABSPATH' ) || exit;

final class HTML_Translator {
	private Translation_Client_Interface $client;
	private Router $router;
	private array $settings;
	private array $protected_strings = array();

	public function __construct( Translation_Client_Interface $client, Router $router, array $settings, array $protected_strings = array() ) {
		$this->client   = $client;
		$this->router   = $router;
		$this->settings = $settings;
		foreach ( $protected_strings as $value ) {
			$value = trim( (string) $value );
			if ( '' !== $value ) {
				$this->protected_strings[ $value ] = true;
			}
		}
	}

	public function translate_document( string $html, string $target_language ): string {
		if ( ! class_exists( '\DOMDocument' ) ) {
			throw new \RuntimeException( esc_html__( 'The PHP DOM extension is required by LocalizePilot.', 'localizepilot' ) );
		}

		$dom = new \DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			throw new \RuntimeException( esc_html__( 'LocalizePilot could not parse the page HTML.', 'localizepilot' ) );
		}

		foreach ( iterator_to_array( $dom->childNodes ) as $child ) {
			if ( XML_PI_NODE === $child->nodeType ) {
				$dom->removeChild( $child );
			}
		}

		$entries = array();
		$unique  = array();
		$this->collect_entries( $dom, $entries, $unique );

		if ( ! empty( $unique ) ) {
			$translated = $this->translate_unique_values( array_keys( $unique ), $target_language );
			foreach ( $entries as $entry ) {
				$value = $translated[ $entry['value'] ] ?? $entry['value'];
				if ( 'text' === $entry['type'] ) {
					$entry['node']->nodeValue = $entry['prefix'] . $value . $entry['suffix'];
				} else {
					$entry['node']->setAttribute( $entry['attribute'], $value );
				}
			}
		}

		if ( ! empty( $this->settings['translate_internal_links'] ) ) {
			$this->rewrite_internal_links( $dom, $target_language );
		}

		$html_node = $dom->getElementsByTagName( 'html' )->item( 0 );
		if ( $html_node instanceof \DOMElement ) {
			$html_node->setAttribute( 'lang', $target_language );
			$html_node->setAttribute( 'dir', Language_Catalog::is_rtl( $target_language ) ? 'rtl' : 'ltr' );
		}

		$output = $dom->saveHTML();
		return is_string( $output ) ? $output : $html;
	}

	public function translate_editor_content( string $content, string $target_language ): string {
		if ( '' === trim( $content ) ) {
			return '';
		}

		$tokens = preg_split(
			'/(<!--[\s\S]*?-->|<\/?[a-zA-Z][^>]*>)/u',
			$content,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);
		if ( ! is_array( $tokens ) ) {
			return $content;
		}

		$ignored_tags = array( 'script', 'style', 'code', 'pre', 'textarea', 'noscript', 'svg', 'math', 'template' );
		$ignored_stack = array();
		$entries = array();
		$attribute_entries = array();
		$unique  = array();
		$attribute_counter = 0;

		foreach ( $tokens as $index => $token ) {
			if ( 0 === strpos( $token, '<!--' ) ) {
				continue;
			}

			if ( preg_match( '/^<\s*\/\s*([a-zA-Z0-9:-]+)/u', $token, $match ) ) {
				$tag = strtolower( $match[1] );
				if ( ! empty( $ignored_stack ) && end( $ignored_stack ) === $tag ) {
					array_pop( $ignored_stack );
				}
				continue;
			}

			if ( preg_match( '/^<\s*([a-zA-Z0-9:-]+)/u', $token, $match ) ) {
				$tag = strtolower( $match[1] );

				if ( empty( $ignored_stack ) && ! empty( $this->settings['translate_attributes'] ) ) {
					$tokens[ $index ] = preg_replace_callback(
						'/\b(alt|title|placeholder|aria-label)\s*=\s*(["\'])(.*?)\2/isu',
						function ( array $attribute_match ) use ( &$attribute_entries, &$unique, &$attribute_counter ): string {
							$value = $attribute_match[3];
							if ( '' === trim( $value ) || ! preg_match( '/[\p{L}\p{N}]/u', $value ) ) {
								return $attribute_match[0];
							}
							$marker = '__LOCALIZEPILOT_ATTRIBUTE_' . $attribute_counter++ . '__';
							$attribute_entries[ $marker ] = $value;
							$unique[ $value ] = true;
							return $attribute_match[1] . '=' . $attribute_match[2] . $marker . $attribute_match[2];
						},
						$token
					) ?? $token;
				}

				if ( in_array( $tag, $ignored_tags, true ) && ! preg_match( '/\/\s*>$/', $token ) ) {
					$ignored_stack[] = $tag;
				}
				continue;
			}

			if ( ! empty( $ignored_stack ) || '' === trim( $token ) || ! preg_match( '/[\p{L}\p{N}]/u', $token ) ) {
				continue;
			}

			preg_match( '/^(\s*)(.*?)(\s*)$/us', $token, $matches );
			$value = $matches[2] ?? trim( $token );
			if ( '' === trim( $value ) || preg_match( '/^\[[^\]]+\]$/s', trim( $value ) ) ) {
				continue;
			}

			$entries[] = array(
				'index'  => $index,
				'value'  => $value,
				'prefix' => $matches[1] ?? '',
				'suffix' => $matches[3] ?? '',
			);
			$unique[ $value ] = true;
		}

		if ( empty( $unique ) ) {
			return $content;
		}

		$translated = $this->translate_unique_values( array_keys( $unique ), $target_language );
		foreach ( $entries as $entry ) {
			$value = $translated[ $entry['value'] ] ?? $entry['value'];
			$tokens[ $entry['index'] ] = $entry['prefix'] . $value . $entry['suffix'];
		}

		foreach ( $attribute_entries as $marker => $source_value ) {
			$value = $translated[ $source_value ] ?? $source_value;
			foreach ( $tokens as $index => $token ) {
				if ( false !== strpos( $token, $marker ) ) {
					$tokens[ $index ] = str_replace( $marker, esc_attr( $value ), $token );
					break;
				}
			}
		}

		return implode( '', $tokens );
	}

	public function translate_fragment( string $html, string $target_language ): string {
		if ( '' === trim( $html ) ) {
			return '';
		}

		$wrapper = '<!DOCTYPE html><html><body><div id="next-translate-fragment-root">' . $html . '</div></body></html>';
		$translated = $this->translate_document( $wrapper, $target_language );

		$dom = new \DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML( '<?xml encoding="UTF-8">' . $translated, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return $html;
		}

		$xpath = new \DOMXPath( $dom );
		$root = $xpath->query( '//*[@id="next-translate-fragment-root"]' )->item( 0 );
		if ( ! $root instanceof \DOMElement ) {
			return $html;
		}

		$output = '';
		foreach ( $root->childNodes as $child ) {
			$output .= $dom->saveHTML( $child );
		}

		return $output;
	}

	/**
	 * @param array<int,array<string,mixed>> $entries
	 * @param array<string,bool> $unique
	 */
	private function collect_entries( \DOMDocument $dom, array &$entries, array &$unique ): void {
		$xpath = new \DOMXPath( $dom );
		$nodes = $xpath->query( '//text()' );

		if ( $nodes ) {
			foreach ( $nodes as $node ) {
				if ( ! $node instanceof \DOMText || $this->is_excluded_node( $node ) ) {
					continue;
				}
				$original = (string) $node->nodeValue;
				if ( '' === trim( $original ) || ! preg_match( '/[\p{L}\p{N}]/u', $original ) ) {
					continue;
				}
				preg_match( '/^(\s*)(.*?)(\s*)$/us', $original, $matches );
				$value = $matches[2] ?? trim( $original );
				if ( '' === trim( $value ) || isset( $this->protected_strings[ $value ] ) ) {
					continue;
				}
				$entries[] = array(
					'type'   => 'text',
					'node'   => $node,
					'value'  => $value,
					'prefix' => $matches[1] ?? '',
					'suffix' => $matches[3] ?? '',
				);
				$unique[ $value ] = true;
			}
		}

		if ( empty( $this->settings['translate_attributes'] ) ) {
			return;
		}

		$elements = $xpath->query( '//*[@alt or @title or @placeholder or @aria-label]' );
		if ( $elements ) {
			foreach ( $elements as $element ) {
				if ( ! $element instanceof \DOMElement || $this->is_excluded_node( $element ) ) {
					continue;
				}
				foreach ( array( 'alt', 'title', 'placeholder', 'aria-label' ) as $attribute ) {
					if ( ! $element->hasAttribute( $attribute ) ) {
						continue;
					}
					$value = trim( $element->getAttribute( $attribute ) );
					if ( '' === $value || isset( $this->protected_strings[ $value ] ) || ! preg_match( '/[\p{L}\p{N}]/u', $value ) ) {
						continue;
					}
					$entries[] = array(
						'type'      => 'attribute',
						'node'      => $element,
						'attribute' => $attribute,
						'value'     => $value,
					);
					$unique[ $value ] = true;
				}
			}
		}

		$meta_nodes = $xpath->query( '//meta[@content]' );
		if ( $meta_nodes ) {
			foreach ( $meta_nodes as $meta ) {
				if ( ! $meta instanceof \DOMElement ) {
					continue;
				}
				$name = strtolower( $meta->getAttribute( 'name' ) );
				$property = strtolower( $meta->getAttribute( 'property' ) );
				$allowed = in_array( $name, array( 'description', 'twitter:title', 'twitter:description' ), true )
					|| in_array( $property, array( 'og:title', 'og:description' ), true );
				if ( ! $allowed ) {
					continue;
				}
				$value = trim( $meta->getAttribute( 'content' ) );
				if ( '' === $value ) {
					continue;
				}
				$entries[] = array(
					'type'      => 'attribute',
					'node'      => $meta,
					'attribute' => 'content',
					'value'     => $value,
				);
				$unique[ $value ] = true;
			}
		}
	}

	/**
	 * @param string[] $values
	 * @return array<string,string>
	 */
	private function translate_unique_values( array $values, string $target_language ): array {
		$map = array();
		$batch = array();
		$characters = 0;

		$flush = function () use ( &$batch, &$characters, &$map, $target_language ): void {
			if ( empty( $batch ) ) {
				return;
			}
			$result = $this->client->translate_batch( $batch, $target_language );
			foreach ( $batch as $index => $source ) {
				$map[ $source ] = $result[ $index ] ?? $source;
			}
			$batch = array();
			$characters = 0;
		};

		foreach ( $values as $value ) {
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
			if ( count( $batch ) >= 20 || ( $characters + $length ) > 9000 ) {
				$flush();
			}
			$batch[] = $value;
			$characters += $length;
		}
		$flush();
		return $map;
	}

	private function rewrite_internal_links( \DOMDocument $dom, string $language ): void {
		$xpath = new \DOMXPath( $dom );
		foreach ( array( array( '//a[@href]', 'href' ) ) as $definition ) {
			$nodes = $xpath->query( $definition[0] );
			if ( ! $nodes ) {
				continue;
			}
			foreach ( $nodes as $node ) {
				if ( ! $node instanceof \DOMElement || $this->is_excluded_node( $node ) ) {
					continue;
				}
				$attribute = $definition[1];
				$node->setAttribute( $attribute, $this->router->localize_url( $node->getAttribute( $attribute ), $language ) );
			}
		}
	}

	private function is_excluded_node( \DOMNode $node ): bool {
		$ignored_tags = array( 'script', 'style', 'code', 'pre', 'textarea', 'noscript', 'svg', 'math', 'template' );
		$current = $node instanceof \DOMElement ? $node : $node->parentNode;

		while ( $current instanceof \DOMElement ) {
			$tag = strtolower( $current->tagName );
			if ( in_array( $tag, $ignored_tags, true ) ) {
				return true;
			}
			$id = $current->getAttribute( 'id' );
			if ( 'wpadminbar' === $id || 0 === strpos( $id, 'localizepilot' ) ) {
				return true;
			}
			$classes = ' ' . trim( $current->getAttribute( 'class' ) ) . ' ';
			if ( false !== strpos( $classes, ' notranslate ' ) || false !== strpos( $classes, ' next-translate-' ) ) {
				return true;
			}
			if ( 'no' === strtolower( $current->getAttribute( 'translate' ) ) ) {
				return true;
			}
			$current = $current->parentNode;
		}
		return false;
	}
}
