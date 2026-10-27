import test from 'node:test';
import assert from 'node:assert/strict';
import {
	extendedIssues,
	videoSource,
} from '../../assets/src/features/question-editor/extendedModel.mjs';

const provider = ( link ) => videoSource( link ).provider;

test( 'YouTube links of every common shape give the same video', () => {
	for ( const link of [
		'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
		'https://youtu.be/dQw4w9WgXcQ?t=5',
		'https://www.youtube.com/embed/dQw4w9WgXcQ',
		'https://www.youtube.com/shorts/dQw4w9WgXcQ',
		'https://m.youtube.com/watch?v=dQw4w9WgXcQ&list=x',
		'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
	] ) {
		assert.equal( provider( link ), 'youtube', link );
		assert.equal( videoSource( link ).id, 'dQw4w9WgXcQ', link );
		assert.equal( videoSource( link ).timed, true );
	}
	assert.equal(
		provider( 'https://www.youtube.com/watch?v=short' ),
		'invalid'
	);
} );

test( 'Google Drive links become the preview player', () => {
	const id = '1AbCdEfGhIjKlMnOpQrStUvWxYz';
	for ( const link of [
		`https://drive.google.com/file/d/${ id }/view?usp=sharing`,
		`https://drive.google.com/open?id=${ id }`,
		`https://drive.google.com/uc?id=${ id }`,
	] ) {
		const source = videoSource( link );
		assert.equal( source.provider, 'drive', link );
		assert.equal(
			source.embed,
			`https://drive.google.com/file/d/${ id }/preview`
		);
		assert.equal( source.timed, false );
	}
	assert.equal(
		provider( 'https://drive.google.com/drive/folders/abc' ),
		'invalid'
	);
} );

test( 'OneDrive needs an embed address; short links are refused', () => {
	assert.equal(
		provider(
			'https://onedrive.live.com/embed?resid=ABC%21123&authkey=%21xyz'
		),
		'onedrive'
	);
	assert.equal(
		videoSource(
			'https://onedrive.live.com/redir?resid=ABC%21123&authkey=%21xyz'
		).embed.startsWith( 'https://onedrive.live.com/embed?' ),
		true
	);
	assert.equal(
		provider(
			'https://contoso-my.sharepoint.com/personal/a/_layouts/15/embed.aspx?UniqueId=1'
		),
		'onedrive'
	);
	assert.equal( provider( 'https://1drv.ms/v/s!AbCdEf' ), 'onedrive-short' );
	assert.equal(
		provider( 'https://onedrive.live.com/about/en-us/' ),
		'invalid'
	);
} );

test( 'pasted embed code is reduced to its address', () => {
	const code =
		'<iframe src="https://onedrive.live.com/embed?resid=ABC%21123&amp;authkey=%21xyz" width="640"></iframe>';
	const source = videoSource( code );
	assert.equal( source.provider, 'onedrive' );
	assert.ok( source.embed.includes( 'resid=ABC%21123&authkey=%21xyz' ) );
} );

test( 'other web addresses are treated as video files, scripts are refused', () => {
	assert.equal( provider( 'https://example.com/clip.mp4' ), 'file' );
	assert.equal( provider( 'javascript:alert(1)' ), 'invalid' );
	assert.equal( provider( 'not a link' ), 'invalid' );
	assert.equal( provider( '' ), 'invalid' );
} );

test( 'a video that cannot be played blocks saving the question', () => {
	const checkpoints = [ { id: 'a', at: 1, prompt: 'q', answer: 'x' } ];
	assert.deepEqual(
		extendedIssues( 'interactive-video', {
			video_url: 'https://1drv.ms/v/s!AbCdEf',
			checkpoints,
		} ),
		[ 'video' ]
	);
	assert.deepEqual(
		extendedIssues( 'interactive-video', {
			video_url: 'https://youtu.be/dQw4w9WgXcQ',
			checkpoints,
		} ),
		[]
	);
} );
