import { secret } from './internal';

export const format = ( value: string ) => `${ value }${ secret }`;
