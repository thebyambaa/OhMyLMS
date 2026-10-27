import { useEffect, useRef, useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	prepareQuizPayload,
	canLeaveQuestion,
	mergeSavedQuiz,
	mergeSavedQuestions,
	failedQuestion,
	newQuestionCard,
	moveOption,
	quizEditSnapshot,
} from './model.mjs';
import { loadQuiz, saveQuiz, removeQuestionFromQuiz } from './api.mjs';
import { publishRevision } from '../question-bank/api.mjs';
import { appendLinkedQuestions } from '../question-bank/model.mjs';
import { __, sprintf } from '@wordpress/i18n';
import { promptSnapshots, quickEditStatus } from './quickEditStatus.mjs';
import {
	promptText,
	questionPrompt,
} from '../question-editor/questionPrompt.mjs';
export function useQuizEditor( { store, chapterId, validate, registerTypes } ) {
	const state = useSelect(
		( select ) => {
			const data = select( store );
			return {
				quizId: data.getSelectedQuizId(),
				quiz: data.getQuiz(),
				question: data.selectQuestion(),
				questions: data.getAllQuestions() || [],
				types: data.getQuizTypes(),
				notice: data.getNotificationMessage(),
				noticeStatus: data.getNotificationStatus(),
			};
		},
		[ store ]
	);
	const actions = useDispatch( store );
	const [ loading, setLoading ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ savedNotice, setSavedNotice ] = useState( '' );
	// A new generation starts on every load; late responses from older generations are ignored.
	const generation = useRef( 0 );
	const inFlight = useRef( false );
	const latest = useRef( state );
	const savedSnapshot = useRef( null );
	const failedSnapshot = useRef( null );
	const saveLatest = useRef( null );
	const savedPrompts = useRef( new Map() );
	const submittedPrompts = useRef( new Map() );
	const editSnapshot = quizEditSnapshot( state.quiz, state.questions );
	latest.current = state;
	useEffect( () => {
		const current = ++generation.current;
		let active = true;
		inFlight.current = false;
		savedSnapshot.current = null;
		failedSnapshot.current = null;
		savedPrompts.current = new Map();
		submittedPrompts.current = new Map();
		setSaving( false );
		if ( state.quizId ) {
			setLoading( true );
			setError( null );
			loadQuiz( state.quizId )
				.then( ( loaded ) => {
					if ( ! active || generation.current !== current ) {
						return;
					}
					savedSnapshot.current = quizEditSnapshot(
						loaded,
						loaded.content || []
					);
					actions.setQuiz( loaded );
					savedPrompts.current = promptSnapshots(
						loaded.content || []
					);
					actions.setAllQuestions( loaded.content || [] );
					actions.setSelectedQuestionId( loaded?.content?.[ 0 ]?.id );
					actions.setQuestion( loaded?.content?.[ 0 ] );
				} )
				.catch( ( cause ) => {
					if ( active ) {
						setError(
							cause.message ||
								__( 'Could not load quiz.', 'ohmylms' )
						);
					}
				} )
				.finally( () => {
					if ( active ) {
						setLoading( false );
					}
				} );
		} else {
			setLoading( false );
		}
		return () => {
			active = false;
			generation.current = current + 1;
		};
	}, [ state.quizId, store, actions ] );
	useEffect( () => {
		if ( ! state.types.length ) {
			registerTypes();
		}
	}, [ state.types.length, registerTypes ] );
	useEffect(
		() => () => {
			actions.resetQuizState();
			actions.resetQuestionState();
		},
		[ store, actions ]
	);
	function validateCurrentQuestion() {
		if ( canLeaveQuestion( state.question, validate ) ) {
			return true;
		}
		actions.setQuizError( true );
		return false;
	}
	function selectQuestion( question ) {
		actions.setSelectedQuestionId( question?.id );
		actions.setQuestion( question );
	}
	/**
	 * Save the quiz; resolves true only when the server accepted every change.
	 * @param root0
	 * @param root0.automatic
	 */
	async function save( { automatic = false } = {} ) {
		if ( inFlight.current || chapterId ) {
			return false;
		}
		if ( automatic ) {
			if (
				! state.questions.every(
					( question ) =>
						question.readonly ||
						( ( ! question.settings?.question_code ||
							!! promptText( questionPrompt( question ) ) ) &&
							canLeaveQuestion( question, validate ) )
				)
			) {
				return false;
			}
		} else if ( ! validateCurrentQuestion() ) {
			return false;
		}
		const current = generation.current;
		const submittedQuiz = state.quiz;
		const submittedQuestions = state.questions;
		const selectedId = state.question?.id;
		inFlight.current = true;
		submittedPrompts.current = promptSnapshots( submittedQuestions );
		setSaving( true );
		setError( null );
		setSavedNotice( '' );
		try {
			const saved = await saveQuiz(
				submittedQuiz.id,
				prepareQuizPayload( submittedQuiz, submittedQuestions )
			);
			if ( current !== generation.current ) {
				return false;
			}
			const now = latest.current;
			savedSnapshot.current = quizEditSnapshot(
				mergeSavedQuiz( saved, submittedQuiz, submittedQuiz ),
				saved.content || []
			);
			failedSnapshot.current = null;
			savedPrompts.current = promptSnapshots( saved.content || [] );
			const questions = mergeSavedQuestions(
				saved.content || [],
				submittedQuestions,
				now.questions,
				saved.saved_ids || []
			);
			actions.setQuiz( mergeSavedQuiz( saved, submittedQuiz, now.quiz ) );
			actions.setAllQuestions( questions );
			const index = submittedQuestions.findIndex(
				( question ) => question.id === selectedId
			);
			const selectedNow = now.question?.id;
			const target =
				questions.find( ( question ) => question.id === selectedNow ) ||
				( index >= 0 &&
				( ( _nullableValue ) =>
					_nullableValue !== null && _nullableValue !== void 0 )(
					saved.saved_ids?.[ index ]
				)
					? questions.find(
							( question ) =>
								Number( question.id ) ===
								Number( saved.saved_ids[ index ] )
						)
					: null ) ||
				questions[ 0 ];
			selectQuestion( target );
			if ( ! automatic ) {
				setSavedNotice( __( 'Saved Successfully', 'ohmylms' ) );
			}
			return true;
		} catch ( cause ) {
			if ( current !== generation.current ) {
				return false;
			}
			failedSnapshot.current = quizEditSnapshot(
				submittedQuiz,
				submittedQuestions
			);
			const failed = failedQuestion( cause, submittedQuestions );
			if ( failed ) {
				selectQuestion(
					latest.current.questions.find(
						( q ) => q.id === failed.id
					) || failed
				);
			}
			setError(
				cause?.code === 'ohmylms_quiz_conflict' ||
					cause?.code === 'ohmylms_question_conflict'
					? __(
							'Someone else changed this quiz. Reload it before saving; your edits were not saved.',
							'ohmylms'
						)
					: cause?.message || __( 'Could not save quiz.', 'ohmylms' )
			);
			return false;
		} finally {
			if ( current === generation.current ) {
				inFlight.current = false;
				setSaving( false );
			}
		}
	}
	saveLatest.current = save;
	useEffect( () => {
		if (
			loading ||
			saving ||
			chapterId ||
			! state.quiz?.id ||
			savedSnapshot.current === null ||
			editSnapshot === savedSnapshot.current ||
			editSnapshot === failedSnapshot.current
		) {
			return;
		}
		const timer = setTimeout(
			() =>
				saveLatest.current( {
					automatic: true,
				} ),
			1200
		);
		return () => clearTimeout( timer );
	}, [ editSnapshot, loading, saving, chapterId, state.quiz?.id ] );
	/** Save, then publish the quiz content as an immutable revision for new attempts. */
	async function publish() {
		if ( ! ( await save() ) ) {
			return;
		}
		try {
			const { revision } = await publishRevision(
				latest.current.quiz.id
			);
			setSavedNotice(
				sprintf(
					/* translators: %d: published version number. */ __(
						'Published revision %d. New attempts use it.',
						'ohmylms'
					),
					revision.revision_no
				)
			);
		} catch ( cause ) {
			setError(
				cause?.message || __( 'Could not publish the quiz.', 'ohmylms' )
			);
		}
	}
	/**
	 * Questions placed from the bank are appended without touching unsaved local edits.
	 * @param content
	 * @param addedIds
	 */
	function appendQuestions( content, addedIds ) {
		actions.setAllQuestions(
			appendLinkedQuestions( latest.current.questions, content, addedIds )
		);
	}
	function replaceQuestion( oldId, replacement ) {
		const questions = latest.current.questions.map( ( question ) =>
			question.id === oldId ? replacement : question
		);
		actions.setAllQuestions( questions );
		selectQuestion( replacement );
	}
	function patchQuestion( id, fields ) {
		const questions = latest.current.questions.map( ( question ) =>
			question.id === id
				? {
						...question,
						...fields,
					}
				: question
		);
		actions.setAllQuestions( questions );
		if ( latest.current.question?.id === id ) {
			actions.setQuestion( {
				...latest.current.question,
				...fields,
			} );
		}
	}
	function addQuestion( source ) {
		const question = newQuestionCard(
			source,
			Math.max(
				Date.now(),
				...latest.current.questions.map( ( q ) => Number( q.id ) + 10 )
			)
		);
		question.order_number = latest.current.questions.length + 1;
		actions.setAllQuestions( [ ...latest.current.questions, question ] );
		selectQuestion( question );
	}
	async function removeQuestion( question ) {
		try {
			if ( ! question.temp ) {
				await removeQuestionFromQuiz(
					latest.current.quiz.id,
					question.id
				);
			}
			const questions = latest.current.questions
				.filter( ( q ) => q.id !== question.id )
				.map( ( q, index ) => ( {
					...q,
					order_number: index + 1,
				} ) );
			actions.setAllQuestions( questions );
			if ( latest.current.question?.id === question.id ) {
				selectQuestion( questions[ 0 ] );
			}
		} catch ( cause ) {
			setError(
				cause.message || __( 'Could not remove question.', 'ohmylms' )
			);
		}
	}
	function moveQuestion( from, to ) {
		actions.setAllQuestions(
			moveOption( latest.current.questions, from, to )
		);
	}
	return {
		...state,
		notice: savedNotice || state.notice,
		noticeStatus: savedNotice ? 'status' : state.noticeStatus,
		loading,
		saving,
		error,
		quickEditStatus: ( question ) =>
			quickEditStatus(
				question,
				savedPrompts.current,
				submittedPrompts.current,
				saving,
				error
			),
		autosaveStatus: saving
			? __( 'Saving…', 'ohmylms' )
			: ( () => {
					if ( error ) {
						return __( 'Changes not saved', 'ohmylms' );
					}
					if ( editSnapshot === savedSnapshot.current ) {
						return __( 'All changes saved', 'ohmylms' );
					}
					return __( 'Waiting for complete question', 'ohmylms' );
				} )(),
		save,
		publish,
		appendQuestions,
		replaceQuestion,
		patchQuestion,
		selectQuestion,
		addQuestion,
		removeQuestion,
		moveQuestion,
		validateCurrentQuestion,
		updateField: ( field, value ) =>
			actions.setQuiz( {
				[ field ]: value,
			} ),
	};
}
