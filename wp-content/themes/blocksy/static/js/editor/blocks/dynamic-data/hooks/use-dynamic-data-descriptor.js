import { useState, useEffect } from '@wordpress/element'
import { __ } from 'ct-i18n'
import { getOptionsForBlock } from 'blocksy-options'

import { getLabelForProvider } from '../utils'
import { useTaxonomies } from '../../query/edit/utils/utils'

const options = getOptionsForBlock('dynamic-data')

const wpFields = () => {
	const isContentBlock = document.body.classList.contains(
		'post-type-ct_content_block'
	)

	return {
		provider: 'wp',
		fields: [
			{
				id: 'title',
				label: __('Title', 'blocksy'),
			},

			{
				id: 'excerpt',
				label: __('Excerpt', 'blocksy'),
			},

			{
				id: 'date',
				label: __('Post Date', 'blocksy'),
			},

			{
				id: 'comments',
				label: __('Comments', 'blocksy'),
			},

			{
				id: 'terms',
				label: __('Terms', 'blocksy'),
			},

			{
				id: 'author',
				label: __('Author', 'blocksy'),
			},

			{
				id: 'featured_image',
				label: __('Featured Image', 'blocksy'),
			},

			{
				id: 'author_avatar',
				label: __('Author Avatar', 'blocksy'),
			},

			...(isContentBlock
				? [
						{
							id: 'archive_title',
							label: __('Archive Title', 'blocksy'),
						},

						{
							id: 'archive_description',
							label: __('Archive Description', 'blocksy'),
						},

						{
							id: 'archive_image',
							label: __('Archive Image', 'blocksy'),
						},
				  ]
				: []),
		],
	}
}

const wooFields = (taxonomies = []) => {
	const hasWoo = typeof window.wc !== 'undefined'

	if (!hasWoo) {
		return []
	}

	const hasBrands = (taxonomies || []).find(
		({ slug }) => slug === 'product_brands'
	)

	return [
		{
			provider: 'woo',
			fields: [
				{
					id: 'price',
					label: __('Price', 'blocksy'),
				},
				{
					id: 'rating',
					label: __('Rating', 'blocksy'),
				},
				{
					id: 'stock_status',
					label: __('Stock Status', 'blocksy'),
				},
				{
					id: 'sku',
					label: __('SKU', 'blocksy'),
				},
				...(hasBrands
					? [
							{
								id: 'brands',
								label: __('Brands', 'blocksy'),
							},
					  ]
					: []),
			],
		},
	]
}

let callbacks = {}

const cache = {}

const fetchDataFor = (postId, postType, taxonomies, cb) => {
	if (cache[postId]) {
		cb(cache[postId])
		return
	}

	if (!callbacks[postId]) {
		callbacks[postId] = [cb]

		fetch(
			`${wp.ajax.settings.url}?action=blocksy_blocks_retrieve_dynamic_data_descriptor`,
			{
				headers: {
					Accept: 'application/json',
					'Content-Type': 'application/json',
				},
				method: 'POST',
				body: JSON.stringify({
					post_id: postId,
				}),
			}
		)
			.then((res) => res.json())
			.then(({ success, data }) => {
				const newData = {
					...data,
					fields: [
						wpFields(),
						...(postType === 'product'
							? wooFields(taxonomies)
							: []),
						...data.fields,
					],
				}

				cache[postId] = newData

				callbacks[postId].map((cb) => cb(newData))
			})

		return
	}

	callbacks[postId].push(cb)
}

const useDynamicDataDescriptor = ({ postId, postType }) => {
	const taxonomies = useTaxonomies(postType)

	const [fieldsDescriptor, setFieldsDescriptor] = useState({
		fields: [
			wpFields(),
			...(postType === 'product' ? wooFields(taxonomies) : []),
		],
	})

	useEffect(() => {
		if (taxonomies) {
			fetchDataFor(postId, postType, taxonomies, (data) => {
				setFieldsDescriptor(data)
			})
		}
	}, [postId, postType, taxonomies])

	return {
		fieldsDescriptor,
		options,
		fieldsChoices: fieldsDescriptor.fields.reduce(
			(acc, currentProvider) => [
				...acc,
				...currentProvider.fields
					.filter((field) => {
						if (
							currentProvider.provider !== 'wp' ||
							field.id !== 'terms'
						) {
							return true
						}

						return taxonomies && taxonomies.length > 0
					})
					.map((field) => ({
						group: getLabelForProvider(currentProvider.provider),
						key: `${currentProvider.provider}:${field.id}`,
						value: field.label,
					})),
			],
			[]
		),
	}
}

export default useDynamicDataDescriptor
