module.exports = {
	...require( '@wordpress/prettier-config' ),
	// .gitattributes stores LF in the repository; accept the CRLF a Windows checkout may still have.
	endOfLine: 'auto',
};
