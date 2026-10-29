import { Notice } from '@wordpress/components';
import {
	createElement,
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import {
	DEFAULT_DELAY,
	addNotice,
	createNotice,
	removeNotice,
} from './model.mjs';

const wrapperStyle = {
	position: 'fixed',
	top: '50px',
	left: '50%',
	transform: 'translateX(-50%)',
	zIndex: 99999999,
	width: 'auto',
	maxWidth: '90%',
	display: 'flex',
	flexDirection: 'column',
	gap: '10px',
};

/**
 * Admin notices. Render `contextHolder` once in the screen and call
 * `openNotificationWithIcon( status, message, delay )` to show one.
 *
 * Same contract the recovered application exposed, so screens migrate by changing the import.
 *
 * @return {{openNotificationWithIcon: Function, contextHolder: import('react').ReactElement}} Notice API.
 */
export function useNotifications() {
	const [ notices, setNotices ] = useState( [] );
	const timers = useRef( new Set() );

	useEffect( () => {
		const pending = timers.current;
		return () => {
			pending.forEach( clearTimeout );
			pending.clear();
		};
	}, [] );

	const openNotificationWithIcon = useCallback(
		( status, message, delay = DEFAULT_DELAY ) => {
			const notice = createNotice( status, message );
			setNotices( ( current ) => addNotice( current, notice ) );
			if ( delay > 0 ) {
				const timer = setTimeout( () => {
					timers.current.delete( timer );
					setNotices( ( current ) =>
						removeNotice( current, notice.id )
					);
				}, delay );
				timers.current.add( timer );
			}
		},
		[]
	);

	const contextHolder = createElement(
		'div',
		{ className: 'ohmylms-notice-wrapper', style: wrapperStyle },
		notices.map( ( { id, status, message } ) =>
			createElement(
				Notice,
				{
					key: id,
					status,
					isDismissible: true,
					onRemove: () =>
						setNotices( ( current ) =>
							removeNotice( current, id )
						),
				},
				message
			)
		)
	);

	return { openNotificationWithIcon, contextHolder };
}
