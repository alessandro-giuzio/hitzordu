<?php
/**
 * Front-end output of the booking block.
 *
 * @package Hitzordu
 *
 * @var array $attributes Block attributes from block.json.
 */

?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by WordPress. ?>>
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside render_widget().
	echo ( new \Hitzordu\Plugin\Plugin() )->render_widget(
		(string) ( $attributes['type'] ?? 'discovery-call' ),
		(string) ( $attributes['contact'] ?? '' )
	);
	?>
</div>
