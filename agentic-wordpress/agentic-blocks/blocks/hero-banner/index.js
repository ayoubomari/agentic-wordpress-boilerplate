import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	SelectControl,
	RangeControl,
} from '@wordpress/components';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const {
			heading,
			subheading,
			ctaText,
			ctaUrl,
			imageUrl,
			overlayOpacity,
			contentAlign,
			height,
		} = attributes;

		const blockProps = useBlockProps( {
			className: [
				'agentic-hero-banner',
				`agentic-hero-banner--align-${ contentAlign }`,
				`agentic-hero-banner--height-${ height }`,
				imageUrl ? 'agentic-hero-banner--has-image' : '',
			]
				.filter( Boolean )
				.join( ' ' ),
			style: imageUrl
				? {
						backgroundImage: `url(${ imageUrl })`,
						'--agentic-hero-overlay': overlayOpacity / 100,
				  }
				: undefined,
		} );

		return (
			<>
				<InspectorControls>
					<PanelBody title="Background">
						<TextControl
							label="Image URL"
							value={ imageUrl }
							onChange={ ( v ) => setAttributes( { imageUrl: v } ) }
							help="Leave empty for a flat background."
						/>
						<RangeControl
							label="Overlay opacity"
							value={ overlayOpacity }
							onChange={ ( v ) => setAttributes( { overlayOpacity: v } ) }
							min={ 0 }
							max={ 100 }
						/>
					</PanelBody>

					<PanelBody title="Layout">
						<SelectControl
							label="Content alignment"
							value={ contentAlign }
							options={ [
								{ label: 'Left', value: 'left' },
								{ label: 'Center', value: 'center' },
								{ label: 'Right', value: 'right' },
							] }
							onChange={ ( v ) => setAttributes( { contentAlign: v } ) }
						/>
						<SelectControl
							label="Height"
							value={ height }
							options={ [
								{ label: 'Small', value: 'small' },
								{ label: 'Medium', value: 'medium' },
								{ label: 'Large', value: 'large' },
							] }
							onChange={ ( v ) => setAttributes( { height: v } ) }
						/>
					</PanelBody>

					<PanelBody title="Call to action">
						<TextControl
							label="Button text"
							value={ ctaText }
							onChange={ ( v ) => setAttributes( { ctaText: v } ) }
						/>
						<TextControl
							label="Button URL"
							value={ ctaUrl }
							onChange={ ( v ) => setAttributes( { ctaUrl: v } ) }
						/>
					</PanelBody>
				</InspectorControls>

				<div { ...blockProps }>
					<div className="agentic-hero-banner__inner">
						<RichText
							tagName="h1"
							className="agentic-hero-banner__heading"
							value={ heading }
							onChange={ ( v ) => setAttributes( { heading: v } ) }
							placeholder="Heading…"
						/>
						<RichText
							tagName="p"
							className="agentic-hero-banner__subheading"
							value={ subheading }
							onChange={ ( v ) => setAttributes( { subheading: v } ) }
							placeholder="Subheading…"
						/>
					</div>
				</div>
			</>
		);
	},
	save: () => null, // dynamic block — render.php handles output
} );
