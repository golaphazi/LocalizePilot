<?php
/**
 * The single source of truth for LocalizePilot console navigation.
 *
 * Both the WordPress admin menu and the console sidebar are generated from
 * this map, so the two can never drift apart.
 *
 * @package LocalizePilot
 */

namespace LocalizePilot\Admin;

defined( 'ABSPATH' ) || exit;

final class Screen_Registry {
	public const PARENT_SLUG = 'localizepilot';

	/**
	 * Sidebar groups, in display order. The empty key holds the ungrouped
	 * items that sit directly under the logo.
	 *
	 * @return array<string,string>
	 */
	public static function groups(): array {
		return array(
			''              => '',
			'workspace'     => __( 'Workspace', 'localizepilot' ),
			'insights'      => __( 'Insights', 'localizepilot' ),
			'configuration' => __( 'Configuration', 'localizepilot' ),
		);
	}

	/**
	 * Every console screen.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		static $screens = null;

		if ( null !== $screens ) {
			return $screens;
		}

		$screens = array(
			'overview'          => array(
				'label'       => __( 'Overview', 'localizepilot' ),
				'description' => __( 'Manage translations, languages, and localization performance from one place.', 'localizepilot' ),
				'icon'        => 'nav-overview',
				'group'       => '',
				'class'       => Screens\Overview_Screen::class,
			),
			'translations'      => array(
				'label'       => __( 'Translations', 'localizepilot' ),
				'description' => __( 'Manage, review, and update translated WordPress content from one place.', 'localizepilot' ),
				'icon'        => 'nav-translations',
				'group'       => 'workspace',
				'class'       => Screens\Translations_Screen::class,
			),
			'languages'         => array(
				'label'       => __( 'Languages', 'localizepilot' ),
				'description' => __( 'Manage the languages available on your site and configure your multilingual URLs.', 'localizepilot' ),
				'icon'        => 'nav-languages',
				'group'       => 'workspace',
				'class'       => Screens\Languages_Screen::class,
			),
			'media'             => array(
				'label'       => __( 'Media', 'localizepilot' ),
				'description' => __( 'Manage localized images and media across your language versions.', 'localizepilot' ),
				'icon'        => 'nav-media',
				'group'       => 'workspace',
				'class'       => Screens\Media_Screen::class,
			),
			'seo-urls'          => array(
				'label'       => __( 'SEO & URLs', 'localizepilot' ),
				'description' => __( 'Manage multilingual URLs, canonical links, and language signals across your site.', 'localizepilot' ),
				'icon'        => 'nav-seo-urls',
				'group'       => 'workspace',
				'class'       => Screens\Seo_Urls_Screen::class,
			),
			'analytics'         => array(
				'label'       => __( 'Analytics', 'localizepilot' ),
				'description' => __( 'Understand visitor activity across your language versions and see which localized pages get the most traffic.', 'localizepilot' ),
				'icon'        => 'nav-analytics',
				'group'       => 'insights',
				'class'       => Screens\Analytics_Screen::class,
			),
			'performance'       => array(
				'label'       => __( 'Performance', 'localizepilot' ),
				'description' => __( 'Monitor translation caching, rendering speed, and multilingual delivery health.', 'localizepilot' ),
				'icon'        => 'nav-performance',
				'group'       => 'insights',
				'class'       => Screens\Performance_Screen::class,
			),
			'providers'         => array(
				'label'       => __( 'Providers', 'localizepilot' ),
				'description' => __( 'Choose the translation service LocalizePilot uses and manage its credentials.', 'localizepilot' ),
				'icon'        => 'nav-providers',
				'group'       => 'configuration',
				'class'       => Screens\Providers_Screen::class,
			),
			'cache-management'  => array(
				'label'       => __( 'Cache Management', 'localizepilot' ),
				'description' => __( 'Manage translated page cache and keep localized content fresh across your site.', 'localizepilot' ),
				'icon'        => 'nav-cache-management',
				'group'       => 'configuration',
				'class'       => Screens\Cache_Management_Screen::class,
			),
			'language-switcher' => array(
				'label'       => __( 'Language Switcher', 'localizepilot' ),
				'description' => __( 'Control how visitors move between the language versions of your site.', 'localizepilot' ),
				'icon'        => 'nav-language-switcher',
				'group'       => 'configuration',
				'class'       => Screens\Language_Switcher_Screen::class,
			),
			'settings'          => array(
				'label'       => __( 'Settings', 'localizepilot' ),
				'description' => __( 'Configure translation behaviour, caching, and analytics collection.', 'localizepilot' ),
				'icon'        => 'nav-settings',
				'group'       => 'configuration',
				'class'       => Screens\Settings_Screen::class,
			),
			'migration'         => array(
				'label'       => __( 'Migration', 'localizepilot' ),
				'description' => __( 'Bring translations over from WPML or Polylang without retranslating them.', 'localizepilot' ),
				'icon'        => 'nav-migration',
				'group'       => 'configuration',
				'class'       => Screens\Migration_Screen::class,
			),
			'license'           => array(
				'label'       => __( 'License Activation', 'localizepilot' ),
				'description' => __( 'Activate your LocalizePilot license to unlock premium features, updates, and support.', 'localizepilot' ),
				'icon'        => 'nav-license',
				'group'       => 'configuration',
				'class'       => Screens\License_Screen::class,
			),
		);

		foreach ( $screens as $slug => $screen ) {
			$screens[ $slug ]['slug']       = $slug;
			$screens[ $slug ]['menu_slug']  = self::menu_slug( $slug );
			$screens[ $slug ]['capability'] = 'manage_options';
			$screens[ $slug ]['url']        = self::url( $slug );
		}

		/**
		 * Filter the console screen map.
		 *
		 * @param array<string,array<string,mixed>> $screens Screen definitions keyed by slug.
		 */
		$screens = (array) apply_filters( 'localizepilot_console_screens', $screens );

		return $screens;
	}

	/**
	 * Screens grouped for sidebar rendering, preserving group order.
	 *
	 * @return array<string,array{label:string,items:array<int,array<string,mixed>>}>
	 */
	public static function grouped(): array {
		$grouped = array();

		foreach ( self::groups() as $key => $label ) {
			$grouped[ $key ] = array(
				'label' => $label,
				'items' => array(),
			);
		}

		foreach ( self::all() as $screen ) {
			$group = (string) ( $screen['group'] ?? '' );

			if ( ! isset( $grouped[ $group ] ) ) {
				$grouped[ $group ] = array(
					'label' => '',
					'items' => array(),
				);
			}

			$grouped[ $group ]['items'][] = $screen;
		}

		return array_filter(
			$grouped,
			static function ( array $group ): bool {
				return ! empty( $group['items'] );
			}
		);
	}

	public static function exists( string $slug ): bool {
		return isset( self::all()[ $slug ] );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get( string $slug ): ?array {
		return self::all()[ $slug ] ?? null;
	}

	/**
	 * The WordPress page slug for a console screen. Overview owns the parent
	 * slug so the top-level menu item opens it.
	 */
	public static function menu_slug( string $slug ): string {
		return 'overview' === $slug ? self::PARENT_SLUG : self::PARENT_SLUG . '-' . $slug;
	}

	/**
	 * Resolve a WordPress page slug back to a console screen slug.
	 */
	public static function slug_from_menu( string $menu_slug ): string {
		foreach ( self::all() as $slug => $screen ) {
			if ( $menu_slug === $screen['menu_slug'] ) {
				return (string) $slug;
			}
		}

		return '';
	}

	public static function url( string $slug ): string {
		return admin_url( 'admin.php?page=' . self::menu_slug( $slug ) );
	}
}
