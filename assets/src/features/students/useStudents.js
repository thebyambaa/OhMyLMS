import { useEffect, useRef, useState } from '@wordpress/element';
import { listStudents, changeStudentAccess } from './api.mjs';
export function useStudents() {
	const [ query, setQuery ] = useState( {
		page: 1,
		perPage: 5,
		search: '',
		dateFilter: '',
		orderby: 'registration_date',
		order: 'DESC',
	} );
	const [ data, setData ] = useState( { students: [], total: 0 } );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ revision, setRevision ] = useState( 0 );
	const [ saving, setSaving ] = useState( false );
	const pending = useRef( false );
	useEffect( () => {
		let active = true;
		setLoading( true );
		setError( null );
		listStudents( query )
			.then( ( result ) => {
				if ( active ) {
					setData( result );
				}
			} )
			.catch( ( cause ) => {
				if ( active ) {
					setError( cause.message || 'Could not load students.' );
				}
			} )
			.finally( () => {
				if ( active ) {
					setLoading( false );
				}
			} );
		return () => {
			active = false;
		};
	}, [ query, revision ] );
	async function changeAccess( ids, blocked ) {
		if ( pending.current || ! ids.length ) {
			return false;
		}
		pending.current = true;
		setSaving( true );
		setError( null );
		try {
			await changeStudentAccess( ids, blocked );
			setRevision( ( value ) => value + 1 );
			return true;
		} catch ( cause ) {
			setError( cause.message || 'Could not update student access.' );
			return false;
		} finally {
			pending.current = false;
			setSaving( false );
		}
	}
	return {
		...data,
		query,
		loading,
		error,
		saving,
		changeAccess,
		reload: () => setRevision( ( value ) => value + 1 ),
		updateQuery: ( changes ) =>
			setQuery( ( current ) => ( { ...current, page: 1, ...changes } ) ),
	};
}
