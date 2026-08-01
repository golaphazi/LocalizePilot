<?php

namespace NextTranslate;

defined( 'ABSPATH' ) || exit;

interface Translation_Client_Interface {
	/**
	 * @param string[] $texts
	 * @return string[]
	 */
	public function translate_batch( array $texts, string $target_language ): array;

	public function test( string $target_language = 'es' ): string;

	public function provider(): string;
}
