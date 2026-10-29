import { createRequire } from 'node:module';

const React = createRequire( import.meta.url )( 'react' );

export const { createElement, memo } = React;
