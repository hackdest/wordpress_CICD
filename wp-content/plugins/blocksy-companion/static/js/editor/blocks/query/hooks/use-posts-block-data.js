import { useEffect, useState } from '@wordpress/element'

import { getStableJsonKey } from 'blocksy-options'

function getJsonFromUrl(url) {
	if (!url) url = location.search
	var query = url.substr(1)
	var result = {}
	query.split('&').forEach(function (part) {
		var item = part.split('=')
		result[item[0]] = decodeURIComponent(item[1])
	})
	return result
}

const cache = {}

export const usePostsBlockData = ({ attributes, previewedPostId }) => {
	const [blockData, setBlockData] = useState(null)

	let [{ controller }, setAbortState] = useState({
		controller: null,
	})

	useEffect(() => {
		const input = {
			attributes,
			previewedPostId,
		}

		const key = getStableJsonKey(input)

		if (cache[key]) {
			setBlockData(cache[key])
		} else {
			if (controller) {
				controller.abort()
			}

			if ('AbortController' in window) {
				controller = new AbortController()

				setAbortState({
					controller,
				})
			}

			let qs = getJsonFromUrl(location.search)

			fetch(
				`${wp.ajax.settings.url}?action=blocksy_get_posts_block_data${
					qs.lang ? '&lang=' + qs.lang : ''
				}`,
				{
					headers: {
						Accept: 'application/json',
						'Content-Type': 'application/json',
					},
					method: 'POST',
					signal: controller.signal,
					body: JSON.stringify({
						attributes,
						previewedPostId,
					}),
				}
			)
				.then((res) => res.json())
				.then(({ success, data }) => {
					if (!success) {
						return
					}

					cache[key] = data

					setAbortState({
						controller: null,
					})

					setBlockData(data)
				})
		}
	}, [attributes, previewedPostId])

	return {
		blockData,
	}
}
