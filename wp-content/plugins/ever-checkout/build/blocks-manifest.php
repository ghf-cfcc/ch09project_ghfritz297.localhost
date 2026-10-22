<?php
// This file is generated. Do not modify it manually.
return array(
	'buy-button' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'ever-checkout/buy-button',
		'title' => 'Buy Button',
		'category' => 'ever-checkout',
		'description' => 'Sells one product. Drop it on any page or post.',
		'keywords' => array(
			'buy',
			'checkout',
			'sell',
			'download',
			'purchase'
		),
		'textdomain' => 'ever-checkout',
		'attributes' => array(
			'productId' => array(
				'type' => 'number',
				'default' => 0
			),
			'label' => array(
				'type' => 'string',
				'default' => ''
			),
			'showPrice' => array(
				'type' => 'boolean',
				'default' => true
			),
			'width' => array(
				'type' => 'number'
			),
			'backgroundColor' => array(
				'type' => 'string'
			),
			'textColor' => array(
				'type' => 'string'
			),
			'gradient' => array(
				'type' => 'string'
			),
			'borderColor' => array(
				'type' => 'string'
			)
		),
		'supports' => array(
			'html' => false,
			'anchor' => true,
			'align' => array(
				'left',
				'center',
				'right'
			),
			'color' => array(
				'__experimentalSkipSerialization' => true,
				'gradients' => true,
				'__experimentalDefaultControls' => array(
					'background' => true,
					'text' => true
				)
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true,
				'__experimentalFontFamily' => true,
				'__experimentalFontWeight' => true,
				'__experimentalFontStyle' => true,
				'__experimentalTextTransform' => true,
				'__experimentalTextDecoration' => true,
				'__experimentalLetterSpacing' => true,
				'__experimentalDefaultControls' => array(
					'fontSize' => true
				)
			),
			'spacing' => array(
				'margin' => true,
				'padding' => array(
					'horizontal',
					'vertical'
				),
				'__experimentalSkipSerialization' => array(
					'padding'
				),
				'__experimentalDefaultControls' => array(
					'padding' => true
				)
			),
			'__experimentalBorder' => array(
				'color' => true,
				'radius' => true,
				'style' => true,
				'width' => true,
				'__experimentalSkipSerialization' => true,
				'__experimentalDefaultControls' => array(
					'color' => true,
					'radius' => true,
					'style' => true,
					'width' => true
				)
			),
			'shadow' => array(
				'__experimentalSkipSerialization' => true
			)
		),
		'styles' => array(
			array(
				'name' => 'fill',
				'label' => 'Fill',
				'isDefault' => true
			),
			array(
				'name' => 'outline',
				'label' => 'Outline'
			)
		),
		'selectors' => array(
			'root' => '.wp-block-ever-checkout-buy-button .wp-block-button__link'
		),
		'editorScript' => 'file:./index.js',
		'style' => array(
			'file:./style-index.css',
			'ever-checkout'
		),
		'viewScript' => 'ever-checkout',
		'render' => 'file:./render.php'
	),
	'receipt' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'ever-checkout/receipt',
		'title' => 'Receipt',
		'category' => 'ever-checkout',
		'description' => 'Shows a customer what they bought and their download links.',
		'keywords' => array(
			'receipt',
			'order',
			'download',
			'thank you'
		),
		'textdomain' => 'ever-checkout',
		'attributes' => array(
			'completedHeading' => array(
				'type' => 'string',
				'default' => ''
			),
			'completedNote' => array(
				'type' => 'string',
				'default' => ''
			),
			'pendingHeading' => array(
				'type' => 'string',
				'default' => ''
			),
			'pendingNote' => array(
				'type' => 'string',
				'default' => ''
			),
			'notFoundHeading' => array(
				'type' => 'string',
				'default' => ''
			),
			'downloadLabel' => array(
				'type' => 'string',
				'default' => ''
			),
			'helpText' => array(
				'type' => 'string',
				'default' => ''
			),
			'showSummary' => array(
				'type' => 'boolean',
				'default' => true
			),
			'buttonStyle' => array(
				'type' => 'string',
				'default' => 'outline',
				'enum' => array(
					'fill',
					'outline'
				)
			)
		),
		'supports' => array(
			'html' => false,
			'anchor' => true,
			'align' => array(
				'wide',
				'full'
			),
			'color' => array(
				'background' => true,
				'text' => true,
				'link' => true
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true,
				'textAlign' => true,
				'__experimentalFontFamily' => true
			),
			'spacing' => array(
				'margin' => true,
				'padding' => true
			)
		),
		'editorScript' => 'file:./index.js',
		'style' => array(
			'file:./style-index.css',
			'wp-block-button'
		),
		'render' => 'file:./render.php'
	)
);
