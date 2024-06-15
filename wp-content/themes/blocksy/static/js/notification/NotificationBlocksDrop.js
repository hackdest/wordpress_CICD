import {
	createElement,
	Component,
	createRef,
	useState,
	useEffect,
	useRef,
} from '@wordpress/element'
import { __, sprintf } from 'ct-i18n'
import $ from 'jquery'

const NotificationBlocksDrop = ({
	initialStatus,
	url,
	pluginUrl,
	pluginLink,
}) => {
	const [pluginStatus, setPluginStatus] = useState('installed')

	const [isLoading, setIsLoading] = useState(false)

	const containerEl = useRef(null)

	useEffect(() => {
		setPluginStatus(initialStatus)
	}, [])

	const dismiss = () => {
		containerEl.current.closest('.notice').remove()

		$.ajax(ajaxurl, {
			type: 'POST',
			data: {
				action: 'blocksy_dismissed_blocks_move_notice_handler',
			},
		})
	}

	return (
		<div className="ct-blocksy-blocks-move-inner" ref={containerEl}>
			<button
				onClick={() => {
					dismiss()
				}}
				type="button"
				className="notice-dismiss">
				<span className="screen-reader-text">
					{__('Dismiss this notice.', 'blocksy')}
				</span>
			</button>

			<span className="ct-notification-icon">
				<svg
					width="50"
					height="50"
					viewBox="0 0 50 50"
					xmlns="http://www.w3.org/2000/svg">
					<path
						fill="#000000"
						d="M50,25C50,11.2,38.8,0,25,0C11.2,0,0,11.2,0,25c0,13.8,11.2,25,25,25C38.8,50,50,38.8,50,25z"
					/>
					<path
						fill="#ffffff"
						d="M23.4,19.5H29c0.7,0,1.3,0.6,1.3,1.4c0,0.8-0.6,1.4-1.4,1.4h-4.4L23.4,19.5z M34.6,25.1c0.9-1.2,1.4-2.7,1.4-4.2c0-1.6-0.5-3-1.4-4.2c-1.3-1.7-3.3-2.9-5.6-2.9c-0.1,0-0.1,0-0.2,0v0H15.5c-0.4,0-0.6,0.4-0.5,0.7l3.2,7.8h-2.8c-0.4,0-0.6,0.4-0.5,0.7l5.6,13.6h8.2c3.9,0,7.1-3.2,7.1-7.1C36,27.8,35.5,26.4,34.6,25.1C34.6,25.2,34.6,25.1,34.6,25.1zM23.4,28H29c0.7,0,1.3,0.6,1.3,1.4c0,0.8-0.6,1.4-1.4,1.4h-4.4L23.4,28z"
					/>
				</svg>
			</span>

			<div className="ct-notification-content">
				<h2>
					{__(
						'Heads up - theme blocks are going to be moved into the Blocksy Companion plugin soon',
						'blocksy'
					)}
				</h2>

				<p
					dangerouslySetInnerHTML={{
						__html:
							__(
								'Due to developer guidelines, all blocks are required to be moved to the Blocksy Companion plugin.',
								'blocksy'
							) +
							'<br>' +
							__(
								'No need to worry, as if you were using any of our theme (Gutenberg) blocks you just need to activate the Blocksy Companion plugin to restore them.',
								'blocksy'
							),
					}}
				/>

				<div className="notice-actions">
					<button
						className="button button-primary"
						disabled={isLoading || pluginStatus === 'active'}
						onClick={() => {
							setIsLoading(true)

							setTimeout(() => {})

							$.ajax(ajaxurl, {
								type: 'POST',
								data: {
									action: 'blocksy_notice_button_click',
									nonce: ct_localizations.nonce,
								},
							}).then(({ success, data }) => {
								if (success) {
									setPluginStatus(data.status)

									if (data.status === 'active') {
										location.assign(pluginUrl)
									}
								}

								setIsLoading(false)
							})
						}}>
						{isLoading
							? __('Installing & activating...', 'blocksy')
							: pluginStatus === 'uninstalled'
							? __('Install Blocksy Companion', 'blocksy')
							: pluginStatus === 'installed'
							? __('Activate Blocksy Companion', 'blocksy')
							: __('Blocksy Companion active!', 'blocksy')}
						{isLoading && (
							<i className="dashicons dashicons-update" />
						)}
					</button>

					<a
						className="ct-why-button button"
						href={'https://creativethemes.com/blocksy/companion/'}>
						{__('Why you need Blocksy Companion?', 'blocksy')}
					</a>
				</div>
			</div>
		</div>
	)
}

export default NotificationBlocksDrop
