import { Card, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { SummaryCard } from './model';

/**
 * Summary figures with the change from the previous period. The change is shown as text and
 * described in words for assistive technology; colour is never the only signal.
 *
 * @param props       Component props.
 * @param props.cards Cards from `buildCards()`.
 * @param props.label Accessible name of the list.
 */
export function SummaryCards( {
	cards,
	label = __( 'Summary', 'ohmylms' ),
}: {
	cards: readonly SummaryCard[];
	label?: string;
} ) {
	return (
		<ul aria-label={ label } style={ { listStyle: 'none', padding: 0 } }>
			{ cards.map( ( card ) => (
				<li
					key={ card.key }
					style={ {
						display: 'inline-block',
						margin: '0 12px 12px 0',
					} }
				>
					<Card>
						<CardBody>
							<div>{ card.label }</div>
							<strong>{ card.value }</strong>
							<div>
								<span aria-hidden="true">{ card.change }</span>
								<span className="screen-reader-text">
									{ card.changeDescription }
								</span>
							</div>
						</CardBody>
					</Card>
				</li>
			) ) }
		</ul>
	);
}
