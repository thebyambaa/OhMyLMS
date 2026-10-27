import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import {
	QUESTION_BLOCK_TYPES,
	questionTypePatch,
} from '../../assets/src/features/question-editor/questionBlocks.mjs';

// Exercise the actual server endpoint without creating quizzes, users or attempts.
test(
	'every built-in type receives a stable, sanitized server preview',
	{
		skip: ! process.env.OHMYLMS_READONLY_WP_ROOT,
	},
	() => {
		const cases = QUESTION_BLOCK_TYPES.map( ( [ type ] ) => {
			const patch = questionTypePatch( { settings: {} }, type, 100 );
			const contentKeys = new Set( [
				'text',
				'label',
				'prompt',
				'passage',
				'sentence',
				'hint',
				'explanation',
				'unit',
			] );
			const inject = ( value ) =>
				Array.isArray( value )
					? value.map( inject )
					: value && typeof value === 'object'
						? Object.fromEntries(
								Object.entries( value ).map(
									( [ key, item ] ) => [
										key,
										typeof item === 'string' &&
										contentKeys.has( key )
											? item + ' {{a}}'
											: inject( item ),
									]
								)
							)
						: value;
			return {
				render: true,
				seed: 123,
				name: 'Question {{a}}',
				description:
					'<p>[[ohmylms-math:latex:inline]]\\frac{ {{a}} }{2}[[/ohmylms-math]] [[ohmylms-math:latex:inline]]\\left\\lbrace\\left\\lbrace a\\right\\rbrace\\right\\rbrace[[/ohmylms-math]]</p><script>alert(1)</script><img src="x" onerror="alert(1)">',
				settings: {
					...inject( patch.settings ),
					hint: 'Hint {{a}}',
					explanation: 'Solution {{a*2}}',
					template: {
						variables: [
							{ name: 'a', type: 'int', min: 2, max: 9 },
						],
					},
				},
				questions: [
					{
						id: 101,
						answer: '{{a}}',
						is_correct: true,
						matching_data: { label: 'Match {{a}}' },
						extension_content: { caption: 'Caption {{a}}' },
					},
				],
			};
		} );
		const result = spawnSync(
			process.env.OHMYLMS_PHP_BIN || 'php',
			[
				...JSON.parse( process.env.OHMYLMS_PHP_ARGS || '[]' ),
				'tests/php/randomized-preview-readonly.php',
				process.env.OHMYLMS_READONLY_WP_ROOT,
			],
			{ input: JSON.stringify( cases ), encoding: 'utf8' }
		);
		assert.equal( result.status, 0, result.stdout + result.stderr );
		assert.match(
			result.stdout,
			new RegExp(
				`${ QUESTION_BLOCK_TYPES.length } randomized question types passed`
			)
		);
	}
);
