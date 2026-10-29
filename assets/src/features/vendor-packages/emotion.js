import { css, jsx, keyframes } from '@emotion/react';
import createCache from '@emotion/cache';

window.ohmylmsVendorPackages = {
	...window.ohmylmsVendorPackages,
	emotion: { css, jsx, keyframes, createCache },
};
