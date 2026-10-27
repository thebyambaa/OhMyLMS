import {
	store,
	getContext,
	getElement,
	withSyncEvent,
} from '@wordpress/interactivity';
import { emit } from 'ohmylms/interactivity';

store( 'ohmylms/curriculum', {
	state: {
		get chapterOpen() {
			const c = getContext();
			return Boolean( c.expanded[ c.chapterId ] );
		},
		get display() {
			const c = getContext();
			return c.expanded[ c.chapterId ] ? 'block' : 'none';
		},
		get chapterDisplay() {
			return getContext().showAll ? 'block' : '';
		},
		get allExpanded() {
			const c = getContext();
			return (
				Object.values( c.expanded ).length > 0 &&
				Object.values( c.expanded ).every( Boolean )
			);
		},
	},
	actions: {
		toggle: withSyncEvent( ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			const c = getContext();
			c.expanded = {
				...c.expanded,
				[ c.chapterId ]: ! c.expanded[ c.chapterId ],
			};
			emit( getElement().ref, 'chapter-toggled', {
				chapterId: c.chapterId,
				open: c.expanded[ c.chapterId ],
			} );
		} ),
		key: withSyncEvent( ( event ) => {
			if ( event.key !== 'Enter' && event.key !== ' ' ) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			const c = getContext();
			c.expanded = {
				...c.expanded,
				[ c.chapterId ]: ! c.expanded[ c.chapterId ],
			};
		} ),
		expand: withSyncEvent( ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			const c = getContext();
			c.expanded = Object.fromEntries(
				Object.keys( c.expanded ).map( ( id ) => [ id, true ] )
			);
		} ),
		collapse: withSyncEvent( ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			const c = getContext();
			c.expanded = Object.fromEntries(
				Object.keys( c.expanded ).map( ( id ) => [ id, false ] )
			);
		} ),
		showAll: withSyncEvent( ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			getContext().showAll = true;
		} ),
	},
} );
