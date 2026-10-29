import { NoticeList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

/** Renders the `core/notices` store. Any feature raises notices with `useDispatch( noticesStore )`. */
export function NoticesRegion() {
	const notices = useSelect(
		( select ) => select( noticesStore ).getNotices(),
		[]
	);
	const { removeNotice } = useDispatch( noticesStore );

	return (
		<NoticeList
			notices={ notices }
			onRemove={ removeNotice }
			className="ohmylms-app-notices"
		/>
	);
}
