const { test, expect } = require( '@playwright/test' );
const { harness } = require( './helpers/recovered-runtime.cjs' );

test( 'the earnings chart preserves its pixels, data, currency labels and update lifecycle', async ( {
	page,
} ) => {
	async function sample( baseline ) {
		const { errors } = await harness(
			page,
			false,
			baseline,
			false,
			baseline
		);
		await page.evaluate( async () => {
			await window.testRequire.e( 655 );
			const component = window.testRequire( 4655 ).default;
			const { t1: Chart } = window.testRequire( 60068 );
			Chart.defaults.animation = false;
			const element = document.getElementById( 'app' );
			element.style.width = '640px';
			element.style.height = '240px';
			window.chartRoot = ReactDOM.createRoot( element );
			window.chartProps = {
				labels: [ 'Mon', 'Tue', 'Wed' ],
				revenueData: [ 100, 200, 300 ],
				refundData: [ 10, 20, 30 ],
				netAmountData: [ 90, 180, 270 ],
				stepSize: 100,
				maxStep: 400,
				tooltipTitle: {
					first: 'Income',
					second: 'Refund',
					third: 'Net income',
				},
				currency: '₮',
				currencyPos: 'right_space',
			};
			window.renderChart = ( changes = {} ) => {
				window.chartProps = { ...window.chartProps, ...changes };
				window.chartRoot.render(
					React.createElement( component, window.chartProps )
				);
			};
			window.renderChart();
		} );
		await expect( page.locator( 'canvas' ) ).toBeVisible();
		await expect
			.poll( () =>
				page.evaluate(
					() =>
						Object.keys( window.testRequire( 60068 ).t1.instances )
							.length
				)
			)
			.toBe( 1 );
		const before = await page.evaluate( () => {
			const canvas = document.querySelector( 'canvas' );
			const chart = window.testRequire( 60068 ).t1.getChart( canvas );
			chart.resize();
			chart.update( 'none' );
			window.initialChart = chart;
			return {
				image: canvas.toDataURL(),
				data: chart.data,
				currencyTick: chart.options.scales.y.ticks.callback( 1200 ),
				currencyTooltip: chart.options.plugins.tooltip.callbacks.label(
					{ dataset: { label: 'Income' }, formattedValue: '1,200' }
				),
				plugins: chart.config.plugins.map( ( plugin ) => plugin.id ),
				width: chart.width,
				height: chart.height,
			};
		} );
		expect( before.currencyTick ).toBe( '1.2k ₮' );
		expect( before.currencyTooltip ).toBe( ' Income: 1,200 ₮' );
		await page.evaluate( () =>
			window.renderChart( {
				labels: [ 'Thu', 'Fri' ],
				revenueData: [ 700, 900 ],
				refundData: [ 0, 10 ],
				netAmountData: [ 700, 890 ],
				maxStep: 1000,
			} )
		);
		await expect
			.poll( () =>
				page.evaluate( () => window.initialChart.data.labels )
			)
			.toEqual( [ 'Thu', 'Fri' ] );
		const after = await page.evaluate( () => {
			const Chart = window.testRequire( 60068 ).t1;
			const chart = Chart.getChart( document.querySelector( 'canvas' ) );
			chart.tooltip.setActiveElements(
				[ { datasetIndex: 0, index: 1 } ],
				{ x: 100, y: 100 }
			);
			chart.update( 'none' );
			return {
				sameInstance: chart === window.initialChart,
				data: chart.data,
				tooltip: chart.tooltip.dataPoints.map( ( point ) => point.raw ),
				image: chart.canvas.toDataURL(),
			};
		} );
		expect( after.sameInstance ).toBe( true );
		expect( after.data.datasets[ 0 ].data ).toEqual( [ 700, 900 ] );
		expect( after.tooltip ).toEqual( [ 900 ] );
		await page.evaluate( () => window.chartRoot.unmount() );
		expect(
			await page.evaluate(
				() =>
					Object.keys( window.testRequire( 60068 ).t1.instances )
						.length
			)
		).toBe( 0 );
		expect( errors ).toEqual( [] );
		return { before, after };
	}
	expect( await sample( false ) ).toEqual( await sample( true ) );
} );

test( 'Emotion preserves serialized styles, prefixed CSS, cache behavior and select controls', async ( {
	page,
} ) => {
	async function sample( baseline ) {
		const { errors } = await harness(
			page,
			false,
			baseline,
			false,
			baseline
		);
		const serialized = await page.evaluate( () => {
			const {
				AH: css,
				i7: keyframes,
				Y: jsx,
			} = window.testRequire( 11456 );
			const animation = keyframes( {
				from: { opacity: 0 },
				to: { opacity: 1 },
			} );
			const style = css( {
				label: 'vendor-contract',
				display: 'flex',
				userSelect: 'none',
				color: '#123456',
				marginTop: 4,
				lineHeight: 1.5,
				'&:hover': { color: '#654321' },
				'@media (min-width: 600px)': { padding: 8 },
				animation: `${ animation } 1s ease`,
			} );
			const cache = window
				.testRequire( 51893 )
				.A( { key: 'contract', nonce: 'test-nonce', speedy: false } );
			cache.insert( '.sample', style, cache.sheet, true );
			const rules = cache.sheet.tags.map( ( tag ) => tag.textContent );
			const nonce = cache.sheet.tags[ 0 ].getAttribute( 'nonce' );
			const inserted = cache.inserted[ style.name ];
			cache.sheet.flush();
			const select = window.testRequire( 46005 ).Ay;
			window.selectRoot = ReactDOM.createRoot(
				document.getElementById( 'app' )
			);
			window.selectRoot.render(
				React.createElement(
					'div',
					null,
					jsx(
						'div',
						{ id: 'emotion-sample', css: style },
						'Styled example'
					),
					React.createElement( select, {
						inputId: 'sample-select',
						'aria-label': 'Course',
						options: [
							{ value: 'math', label: 'Математик' },
							{ value: 'physics', label: 'Physics' },
						],
						onChange: ( value ) => {
							window.selectedValue = value.value;
						},
					} )
				)
			);
			return {
				style,
				animation: {
					name: animation.name,
					styles: animation.styles,
					token: String( animation ),
				},
				rules,
				nonce,
				inserted,
				flushed: cache.sheet.tags.length,
			};
		} );
		await expect( page.locator( '#emotion-sample' ) ).toHaveCSS(
			'color',
			'rgb(18, 52, 86)'
		);
		await page.getByRole( 'combobox', { name: 'Course' } ).click();
		await expect(
			page.getByRole( 'option', { name: 'Математик' } )
		).toBeVisible();
		await page.getByRole( 'option', { name: 'Математик' } ).click();
		expect( await page.evaluate( () => window.selectedValue ) ).toBe(
			'math'
		);
		const rendered = await page.evaluate( () => ( {
			className: document.getElementById( 'emotion-sample' ).className,
			css: [
				...document.querySelectorAll( 'style[data-emotion^="css"]' ),
			].flatMap( ( tag ) =>
				[ ...tag.sheet.cssRules ].map( ( rule ) => rule.cssText )
			),
		} ) );
		expect( errors ).toEqual( [] );
		return { serialized, rendered };
	}
	expect( await sample( false ) ).toEqual( await sample( true ) );
} );
