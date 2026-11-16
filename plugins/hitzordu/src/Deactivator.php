<?php
/**
 * Runs on plugin deactivation.
 *
 * @package Hitzordu
 */

namespace Hitzordu\Plugin;

/**
 * Deactivation tasks. Empty for now: deactivating keeps all data.
 */
class Deactivator {

	/**
	 * Called by register_deactivation_hook().
	 */
	public static function deactivate(): void {
	}
}
