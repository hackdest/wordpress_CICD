import { createElement } from '@wordpress/element'
import { useSelect } from '@wordpress/data'
import { __ } from 'ct-i18n'

const BrandsPreview = ({ product, attributes }) => {
	const { product_brands } = useSelect((select) => {
		return {
			product_brands:
				select('core').getEntityRecords('taxonomy', 'product_brands', {
					per_page: -1,
					post: product.id,
				}) || [],
		}
	})

	if (product_brands.length === 0) {
		return ''
	}

	return (
		<div
			className="ct-product-brands"
			style={{
				'--product-brand-logo-size': `${attributes.brands_size}px`,
				'--product-brands-gap': `${attributes.brands_gap}px`,
			}}>
			{product_brands.map((t, index) =>
				t?.logo?.url ? (
					<span
						key={index}
						className="ct-media-container ct-product-brand">
						<img src={t.logo.url} alt={t.name} />
					</span>
				) : (
					<span
						key={index}
						dangerouslySetInnerHTML={{ __html: t.name }}
					/>
				)
			)}
		</div>
	)
}

export default BrandsPreview
